<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle;

use AnzuSystems\CoreDamBundle\Domain\Asset\AssetManager;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Messenger\Message\TtsMediaStatusCallbackMessage;
use AnzuSystems\CoreDamBundle\Model\Enum\MediaStatusType;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsAudioStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestMode;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

/**
 * Terminal failure handling for a TTS request, shared by the worker (live failure) and the stuck-request
 * cleanup cron. Marks the request Failed (which frees its idempotency key for a fresh dispatch), unwinds the
 * partial aggregate per mode, and notifies the ext system. Never throws — failure handling is best-effort.
 */
final readonly class TtsRequestFailer
{
    public function __construct(
        private TtsNarrationRequestManager $requestManager,
        private TtsAssetLocker $assetLocker,
        private TtsAssetManager $ttsAssetManager,
        private AssetManager $assetManager,
        private AssetRepository $assetRepo,
        private TtsAudioFileRemover $audioFileRemover,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
        private DamLogger $logger,
    ) {
    }

    public function fail(TtsNarrationRequest $request, string $failureReason): void
    {
        // Already terminal (e.g. Done after a post-completion enrichment failure): don't flip status or
        // fire a failure callback that would delete the good media.
        if ($request->getStatus()->in(TtsRequestStatus::TERMINAL_STATUSES)) {
            return;
        }

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
            $this->logger->error(DamLogger::NAMESPACE_TTS, 'requestFailer.markFailedFailed', [
                'requestId' => (string) $request->getId(),
            ], exception: $failureEx);

            return;
        }

        // Initial failed: drop the whole partial aggregate (reserved shell + any attached audio + its TtsAsset)
        // so it doesn't linger AND doesn't shadow future dispatch idempotency (a left-over Active TtsAsset would
        // block future content-keyed dedup forever).
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
        $assetId = $request->getAssetId();
        if (null === $assetId) {
            return;
        }

        try {
            $asset = $this->assetRepo->find($assetId);
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
            $this->logger->warning(DamLogger::NAMESPACE_TTS, 'requestFailer.deleteInitialAssetFailed', [
                'requestId' => (string) $request->getId(),
                'assetId' => $assetId,
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
        $assetId = $request->getAssetId();
        if (null === $assetId) {
            return;
        }

        try {
            $this->entityManager->wrapInTransaction(function () use ($assetId): void {
                $ttsAsset = $this->assetLocker->lock($assetId);
                if ($ttsAsset->getStatus()->is(TtsAudioStatus::Superseding)) {
                    $this->ttsAssetManager->markActive($ttsAsset, flush: true);
                }
            });
        } catch (Throwable $releaseEx) {
            $this->logger->warning(DamLogger::NAMESPACE_TTS, 'requestFailer.releaseStableAssetFailed', [
                'requestId' => (string) $request->getId(),
                'assetId' => $assetId,
                'error' => $releaseEx->getMessage(),
            ]);
        }
    }

    private function dispatchFailureCallback(TtsNarrationRequest $request, string $failureReason): void
    {
        $assetId = $request->getAssetId();
        if (null === $assetId) {
            return;
        }

        // Dispatched to pub/sub so a transient CMS outage is retried by the transport — never swallowed.
        $this->messageBus->dispatch(new TtsMediaStatusCallbackMessage(
            extSystemId: $request->getExtSystemId(),
            assetId: $assetId,
            status: MediaStatusType::GenerationFailed,
            failureReason: $failureReason,
        ));
    }
}
