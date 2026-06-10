<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle;

use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Messenger\Message\TtsMediaStatusCallbackMessage;
use AnzuSystems\CoreDamBundle\Model\Enum\MediaStatusType;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsAudioStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestMode;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

/** Shared terminal failure handler for worker and cron; marks Failed, unwinds per-mode, notifies ext system. Never throws. */
final readonly class TtsRequestFailer
{
    public function __construct(
        private TtsNarrationRequestManager $requestManager,
        private TtsAssetLocker $assetLocker,
        private TtsAssetManager $ttsAssetManager,
        private TtsReservedAssetRemover $reservedAssetRemover,
        private TtsChunkCleaner $chunkCleaner,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
        private DamLogger $logger,
        private TtsLocker $ttsLocker,
    ) {
    }

    public function fail(TtsNarrationRequest $request, string $failureReason): void
    {
        try {
            // Cleanup runs unlocked (storage IO and bus publish must not burn the lock TTL); once
            // Failed is committed under the request lock, every contender bails on its terminal guard.
            $failed = $this->ttsLocker->withRequestLock(
                $request,
                function () use ($request, $failureReason): bool {
                    // Guard: post-Done enrichment errors must not flip status or fire a deletion callback.
                    if ($request->getStatus()->in(TtsRequestStatus::TERMINAL_STATUSES)) {
                        return false;
                    }

                    $this->requestManager->markFailed($request, $failureReason);

                    return true;
                },
            );

            if (false === $failed) {
                return;
            }

            // Regen failed before swap — old audio still valid; release stable asset back to Active.
            if ($request->getMode()->is(TtsRequestMode::Regenerate)) {
                $this->releaseStableAssetOnRegenFailure($request);
            }

            // Initial failed: drop the partial aggregate so a stale TtsAsset cannot shadow future dedup.
            if ($request->getMode()->is(TtsRequestMode::Initial)) {
                $this->reservedAssetRemover->remove($request->getAssetId(), (string) $request->getId());
            }

            $this->chunkCleaner->purge($request);

            $this->dispatchFailureCallback($request, $failureReason);
        } catch (Throwable $failureEx) {
            // Never throws: an unfailable request stays Processing and the cleanup-stuck cron's
            // hard cap terminates it later.
            $this->logger->error(DamLogger::NAMESPACE_TTS, 'requestFailer.failed', [
                'requestId' => (string) $request->getId(),
            ], exception: $failureEx);
        }
    }

    /**
     * Resets stable TtsAsset Superseding→Active; skips if concurrent cancel already took ownership.
     */
    private function releaseStableAssetOnRegenFailure(TtsNarrationRequest $request): void
    {
        $assetId = $request->getAssetId();
        if (null === $assetId) {
            return;
        }

        try {
            $this->ttsLocker->withAssetLock($assetId, function () use ($assetId): void {
                $this->entityManager->wrapInTransaction(function () use ($assetId): void {
                    $ttsAsset = $this->assetLocker->lock($assetId);
                    if ($ttsAsset->getStatus()->is(TtsAudioStatus::Superseding)) {
                        $this->ttsAssetManager->markActive($ttsAsset, flush: true);
                    }
                });
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

        $this->messageBus->dispatch(new TtsMediaStatusCallbackMessage(
            extSystemId: $request->getExtSystemId(),
            assetId: $assetId,
            status: MediaStatusType::GenerationFailed,
            failureReason: $failureReason,
        ));
    }
}
