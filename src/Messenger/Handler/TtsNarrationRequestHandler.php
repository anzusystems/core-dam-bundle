<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Handler;

use AnzuSystems\CoreDamBundle\Domain\Asset\AssetManager;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackFacade;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetLocker;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAudioFileRemover;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsNarrationRequestManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline\TtsRequestOrchestrator;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Messenger\Message\TtsNarrationRequestMessage;
use AnzuSystems\CoreDamBundle\Model\Enum\MediaStatusType;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsAudioStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestMode;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Repository\TtsNarrationRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/**
 * `dam-tts` queue worker. Synth (slow HTTP) and ffmpeg run OUTSIDE any DB transaction — only short
 * persist+commit windows hold locks. Request pipeline lives in {@see TtsRequestOrchestrator}.
 */
#[AsMessageHandler]
final readonly class TtsNarrationRequestHandler
{
    public function __construct(
        private TtsNarrationRequestRepository $requestRepo,
        private TtsNarrationRequestManager $requestManager,
        private TtsRequestOrchestrator $orchestrator,
        private EntityManagerInterface $entityManager,
        private DamLogger $logger,
        private ExtSystemCallbackFacade $extSystemCallbackFacade,
        private TtsAssetLocker $assetLocker,
        private TtsAssetManager $ttsAssetManager,
        private AssetManager $assetManager,
        private AssetRepository $assetRepo,
        private TtsAudioFileRemover $audioFileRemover,
    ) {
    }

    public function __invoke(TtsNarrationRequestMessage $message): void
    {
        $request = $this->claimForProcessing($message->requestId);
        if (null === $request) {
            // Either not found (logged inside claim) or already past Waiting — another worker
            // claimed it via Pub/Sub redelivery. Ack the message and stop.
            return;
        }

        try {
            match ($request->getMode()) {
                TtsRequestMode::Initial => $this->orchestrator->processInitial($request),
                TtsRequestMode::Regenerate => $this->orchestrator->processRegenerate($request),
            };
        } catch (Throwable $e) {
            $this->logger->error(DamLogger::NAMESPACE_TTS, 'handler.requestFailed', [
                'requestId' => (string) $request->getId(),
                'mode' => $request->getMode()->value,
            ], exception: $e);

            // Never rethrow — Messenger retry would re-process a terminal request (openInitialKey
            // is already cleared). Callers must dispatch a fresh request for a retry.
            $this->handleRequestFailure($request, $e);
        }
    }

    /**
     * Atomic Waiting → Processing transition under a row lock. Required because Pub/Sub may
     * redeliver the same message (ack-deadline expiry, worker crash mid-processing) — without
     * this guard two workers would race and double-synthesise.
     */
    private function claimForProcessing(string $requestId): ?TtsNarrationRequest
    {
        return $this->entityManager->wrapInTransaction(function () use ($requestId): ?TtsNarrationRequest {
            $request = $this->requestRepo->findForUpdate($requestId);
            if (null === $request) {
                $this->logger->error(DamLogger::NAMESPACE_TTS, 'handler.requestNotFound', ['requestId' => $requestId]);

                return null;
            }

            if ($request->getStatus()->isNot(TtsRequestStatus::Waiting)) {
                $this->logger->warning(DamLogger::NAMESPACE_TTS, 'handler.alreadyClaimed', [
                    'requestId' => $requestId,
                    'status' => $request->getStatus()->value,
                ]);

                return null;
            }

            $this->requestManager->markProcessing($request);

            return $request;
        });
    }

    private function handleRequestFailure(TtsNarrationRequest $request, Throwable $e): void
    {
        // Already terminal (e.g. Done after a post-completion enrichment failure): don't flip status or
        // fire a failure callback that would delete the good media.
        if ($request->getStatus()->in(TtsRequestStatus::TERMINAL_STATUSES)) {
            return;
        }

        $failureReason = $e->getMessage();

        // Regen failed before the swap: the old audio is still valid, so release the stable asset back to
        // Active rather than leaving it stuck in Superseding.
        if ($request->getMode()->is(TtsRequestMode::Regenerate)) {
            $this->releaseStableAssetOnRegenFailure($request);
        }

        try {
            $this->entityManager->wrapInTransaction(
                function () use ($request, $failureReason): void {
                    $this->requestManager->markFailed($request, $failureReason, false);
                    $this->entityManager->flush();
                }
            );
        } catch (Throwable $failureEx) {
            $this->logger->error(DamLogger::NAMESPACE_TTS, 'handler.markFailedFailed', [
                'requestId' => (string) $request->getId(),
            ], exception: $failureEx);

            return;
        }

        // Initial failed: drop the whole partial aggregate (reserved shell + any attached audio + its TtsAsset)
        // so it doesn't linger AND doesn't shadow future dispatch idempotency (a left-over Active TtsAsset would
        // make findActiveByExt return a broken asset forever).
        if ($request->getMode()->is(TtsRequestMode::Initial)) {
            $this->deleteInitialAssetOnFailure($request);
        }

        $this->dispatchFailureCallback($request, $failureReason);
    }

    /**
     * Best-effort cleanup of the reserved asset after an initial generation failed: remove any audio already
     * attached (file + routes + storage) and then the asset itself, which cascade-deletes its TtsAsset. Covers
     * both the still-empty shell (synth failed before attach) and a partially-built asset (failed after attach).
     */
    private function deleteInitialAssetOnFailure(TtsNarrationRequest $request): void
    {
        $stableAssetId = $request->getStableAssetId();
        if (null === $stableAssetId) {
            return;
        }

        try {
            $asset = $this->assetRepo->find($stableAssetId);
            if (null === $asset) {
                return;
            }

            $audioFiles = [];
            foreach ($asset->getSlots() as $slot) {
                $audio = $slot->getAudio();
                if (null !== $audio) {
                    $audioFiles[] = $audio;
                }
            }
            $this->audioFileRemover->remove(...$audioFiles);

            $this->assetManager->delete($asset, true);
        } catch (Throwable $deleteEx) {
            $this->logger->warning(DamLogger::NAMESPACE_TTS, 'handler.deleteInitialAssetFailed', [
                'requestId' => (string) $request->getId(),
                'stableAssetId' => $stableAssetId,
                'error' => $deleteEx->getMessage(),
            ]);
        }
    }

    /**
     * Resets the stable TtsAsset from Superseding back to Active after a failed regeneration. Skips the
     * asset if a concurrent cancel already moved it out of Superseding (that flow owns the final state).
     */
    private function releaseStableAssetOnRegenFailure(TtsNarrationRequest $request): void
    {
        $stableAssetId = $request->getStableAssetId();
        if (null === $stableAssetId) {
            return;
        }

        try {
            $this->entityManager->wrapInTransaction(function () use ($stableAssetId): void {
                $ttsAsset = $this->assetLocker->lock($stableAssetId);
                if ($ttsAsset->getStatus()->is(TtsAudioStatus::Superseding)) {
                    $this->ttsAssetManager->markActive($ttsAsset, flush: true);
                }
            });
        } catch (Throwable $releaseEx) {
            $this->logger->warning(DamLogger::NAMESPACE_TTS, 'handler.releaseStableAssetFailed', [
                'requestId' => (string) $request->getId(),
                'stableAssetId' => $stableAssetId,
                'error' => $releaseEx->getMessage(),
            ]);
        }
    }

    private function dispatchFailureCallback(TtsNarrationRequest $request, string $failureReason): void
    {
        $extResourceName = $request->getExtRef()->getExtResourceName();
        $extId = $request->getExtRef()->getExtId();
        if (null === $extResourceName || null === $extId) {
            return;
        }

        $this->extSystemCallbackFacade->notifyMediaStatusBestEffort(
            extSystemId: $request->getExtSystemId(),
            extResourceName: $extResourceName,
            extId: $extId,
            assetId: (string) $request->getStableAssetId(),
            status: MediaStatusType::GenerationFailed,
            failureReason: $failureReason,
        );
    }
}
