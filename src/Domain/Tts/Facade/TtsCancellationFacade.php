<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Facade;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetLocker;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsChunkCleaner;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsLifecycle;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsLocker;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsNarrationRequestManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsReservedAssetRemover;
use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Exception\ImmutableAudioNarrationException;
use AnzuSystems\CoreDamBundle\Logger\TtsAuditLogger;
use AnzuSystems\CoreDamBundle\Messenger\Message\TtsMediaStatusCallbackMessage;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\CancelledCallbackData;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\RegenCancelOutcome;
use AnzuSystems\CoreDamBundle\Model\Enum\MediaStatusType;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsAudioStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestMode;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestStatus;
use AnzuSystems\CoreDamBundle\Repository\TtsNarrationRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/** Two-phase regen cancel: phase 1 flags Cancelling + signals in-flight request; phase 2 resolves race with orchestrator swap. */
final readonly class TtsCancellationFacade
{
    public function __construct(
        private TtsAssetLocker $assetLocker,
        private TtsNarrationRequestRepository $requestRepo,
        private TtsNarrationRequestManager $requestManager,
        private TtsAssetManager $ttsAssetManager,
        private TtsChunkCleaner $chunkCleaner,
        private TtsReservedAssetRemover $reservedAssetRemover,
        private TtsAuditLogger $auditLogger,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
        private TtsLocker $ttsLocker,
    ) {
    }

    /**
     * @throws ImmutableAudioNarrationException if the request status is not in CANCELLABLE_STATUSES
     */
    public function cancel(TtsNarrationRequest $request, ?string $userId): bool
    {
        App::throwOnReadOnlyMode();

        if (false === $request->getStatus()->in(TtsRequestStatus::CANCELLABLE_STATUSES)) {
            throw new ImmutableAudioNarrationException(sprintf(
                'Request "%s" cannot be cancelled in status "%s".',
                (string) $request->getId(),
                $request->getStatus()->value,
            ));
        }

        $cancelled = match ($request->getMode()) {
            TtsRequestMode::Initial => $this->cancelInitial($request, $userId),
            TtsRequestMode::Regenerate => $this->cancelRegenerate((string) $request->getAssetId(), $userId),
        };

        if ($cancelled) {
            // Terminal cancel bypasses the failer's guard — purge chunks explicitly.
            $this->chunkCleaner->purge($request);
        }

        return $cancelled;
    }

    private function cancelInitial(TtsNarrationRequest $request, ?string $userId): bool
    {
        // Cleanup runs unlocked: once Cancelled is committed under the request lock, every
        // contender (worker finalize, failer) bails on its own terminal guard.
        $callbackData = $this->ttsLocker->withRequestLock(
            $request,
            function () use ($request, $userId): ?CancelledCallbackData {
                if (false === $request->getStatus()->in(TtsRequestStatus::CANCELLABLE_STATUSES)) {
                    throw new ImmutableAudioNarrationException(sprintf('Request "%s" no longer cancellable.', (string) $request->getId()));
                }

                $this->requestManager->markCancelled($request, flush: true);
                $this->auditLogger->logInitialCancelled((string) $request->getId(), $userId);

                $assetId = $request->getAssetId();

                return null !== $assetId
                    ? new CancelledCallbackData($request->getExtSystemId(), $assetId)
                    : null;
            },
        );

        if (null !== $callbackData) {
            $this->dispatchCancelledCallback($callbackData);
            $this->reservedAssetRemover->remove($callbackData->assetId, (string) $request->getId());
        }

        return true;
    }

    private function cancelRegenerate(string $stableAssetId, ?string $userId): bool
    {
        // Shares the asset lock with AssetSwap::promote() — the swap either committed before
        // phase 1 (cancel is rejected) or runs after phase 2 and aborts.
        $outcome = $this->ttsLocker->withAssetLock($stableAssetId, function () use ($stableAssetId, $userId): RegenCancelOutcome {
            $this->entityManager->wrapInTransaction(fn () => $this->requestStop($stableAssetId));

            return $this->entityManager->wrapInTransaction(
                fn (): RegenCancelOutcome => $this->finalizeRegen($stableAssetId, $userId)
            );
        });

        if (null !== $outcome->callbackData) {
            $this->dispatchCancelledCallback($outcome->callbackData);
        }

        return $outcome->cancelled;
    }

    private function requestStop(string $stableAssetId): void
    {
        $ttsAsset = $this->assetLocker->lockExpecting($stableAssetId, TtsLifecycle::SUPERSEDING_ONLY);

        $this->ttsAssetManager->markCancelling($ttsAsset);

        $activeRegen = $this->requestRepo->findActiveRegenForStable($stableAssetId);
        if (null !== $activeRegen) {
            $this->requestManager->markCancellationRequested($activeRegen);
        }

        $this->entityManager->flush();
    }

    private function finalizeRegen(
        string $stableAssetId,
        ?string $userId,
    ): RegenCancelOutcome {
        $ttsAsset = $this->assetLocker->lock($stableAssetId);

        return match ($ttsAsset->getStatus()) {
            TtsAudioStatus::Cancelling => $this->finalizeWonRace($ttsAsset, $userId),
            default => new RegenCancelOutcome(cancelled: false, callbackData: null),
        };
    }

    private function finalizeWonRace(
        TtsAsset $ttsAsset,
        ?string $userId,
    ): RegenCancelOutcome {
        $assetId = (string) $ttsAsset->getAsset()->getId();
        $activeRegen = $this->requestRepo->findActiveRegenForStable($assetId);

        // Swap never ran — old audio is intact; restore Active, not Failed.
        $this->ttsAssetManager->markActive($ttsAsset);

        $callbackData = null;
        if (null !== $activeRegen) {
            $this->requestManager->markCancelled($activeRegen);
            $callbackData = new CancelledCallbackData($activeRegen->getExtSystemId(), $assetId);
        }

        $this->auditLogger->logCancelled($assetId, (string) $activeRegen?->getId(), $userId);

        $this->entityManager->flush();

        return new RegenCancelOutcome(cancelled: true, callbackData: $callbackData);
    }

    private function dispatchCancelledCallback(CancelledCallbackData $callbackData): void
    {
        $this->messageBus->dispatch(new TtsMediaStatusCallbackMessage(
            extSystemId: $callbackData->extSystemId,
            assetId: $callbackData->assetId,
            status: MediaStatusType::GenerationFailed,
            failureReason: 'Cancelled by admin',
        ));
    }
}
