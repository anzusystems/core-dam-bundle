<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Facade;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackFacade;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetLocker;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsLifecycle;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsNarrationRequestManager;
use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Exception\ImmutableAudioNarrationException;
use AnzuSystems\CoreDamBundle\Logger\TtsAuditLogger;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\CancelledCallbackData;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\RegenCancelOutcome;
use AnzuSystems\CoreDamBundle\Model\Enum\MediaStatusType;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsAudioStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestMode;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestStatus;
use AnzuSystems\CoreDamBundle\Repository\TtsNarrationRequestRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Two-phase regen cancel: phase 1 flips superseding→cancelling on TtsAsset + signals the in-flight
 * request via {@see TtsNarrationRequest::$cancelRequested}; phase 2 re-locks after commit and
 * resolves the race with the orchestrator's swap. Initial cancel mutates only the request
 * (no asset exists yet).
 *
 * Returns true when the request was successfully cancelled (HTTP 200), false when it could not be
 * cancelled because the terminal state was already reached (HTTP 409).
 */
final readonly class TtsCancellationFacade
{
    public function __construct(
        private TtsAssetLocker $assetLocker,
        private TtsNarrationRequestRepository $requestRepo,
        private TtsNarrationRequestManager $requestManager,
        private TtsAssetManager $ttsAssetManager,
        private TtsAuditLogger $auditLogger,
        private EntityManagerInterface $entityManager,
        private ExtSystemCallbackFacade $extSystemCallbackFacade,
    ) {
    }

    /**
     * Returns true when cancelled (caller → HTTP 200 with the refreshed entity),
     * false when the request can no longer be cancelled (caller → HTTP 409).
     *
     * @throws ImmutableAudioNarrationException if the request status is not in CANCELLABLE_STATUSES
     */
    public function execute(TtsNarrationRequest $request, ?string $userId): bool
    {
        App::throwOnReadOnlyMode();

        if (false === $request->getStatus()->in(TtsRequestStatus::CANCELLABLE_STATUSES)) {
            throw new ImmutableAudioNarrationException(sprintf(
                'Request "%s" cannot be cancelled in status "%s".',
                (string) $request->getId(),
                $request->getStatus()->value,
            ));
        }

        return match ($request->getMode()) {
            TtsRequestMode::Initial => $this->cancelInitial($request, $userId),
            TtsRequestMode::Regenerate => $this->cancelRegenerate((string) $request->getStableAssetId(), $userId),
        };
    }

    private function cancelInitial(TtsNarrationRequest $request, ?string $userId): bool
    {
        $callbackData = $this->entityManager->wrapInTransaction(
            function () use ($request, $userId): ?CancelledCallbackData {
                // Re-lock for status consistency under concurrency.
                $locked = $this->requestRepo->find((string) $request->getId(), LockMode::PESSIMISTIC_WRITE);
                if (null === $locked || false === $locked->getStatus()->in(TtsRequestStatus::CANCELLABLE_STATUSES)) {
                    throw new ImmutableAudioNarrationException(sprintf('Request "%s" no longer cancellable.', (string) $request->getId()));
                }

                $this->requestManager->markCancelled($locked);
                $this->auditLogger->logInitialCancelled((string) $locked->getId(), $userId);

                $stableAssetId = $locked->getStableAssetId();
                $callbackData = null !== $stableAssetId
                    ? new CancelledCallbackData($locked->getExtSystemId(), $stableAssetId)
                    : null;

                $this->entityManager->flush();

                return $callbackData;
            }
        );

        if (null !== $callbackData) {
            $this->dispatchCancelledCallback($callbackData);
        }

        return true;
    }

    private function cancelRegenerate(string $stableAssetId, ?string $userId): bool
    {
        $this->entityManager->wrapInTransaction(fn () => $this->requestStop($stableAssetId));

        $outcome = $this->entityManager->wrapInTransaction(
            fn (): RegenCancelOutcome => $this->finalizeRegen($stableAssetId, $userId)
        );

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
            // SwapCompleted or AlreadyFailed — both collapse to "can't cancel" (409)
            default => new RegenCancelOutcome(cancelled: false, callbackData: null),
        };
    }

    private function finalizeWonRace(
        TtsAsset $ttsAsset,
        ?string $userId,
    ): RegenCancelOutcome {
        $assetId = (string) $ttsAsset->getAsset()->getId();
        $activeRegen = $this->requestRepo->findActiveRegenForStable($assetId);

        // The swap never ran, so the old audio is intact — return the asset to Active, not Failed.
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
        $this->extSystemCallbackFacade->notifyMediaStatusBestEffort(
            extSystemId: $callbackData->extSystemId,
            assetId: $callbackData->assetId,
            status: MediaStatusType::GenerationFailed,
            failureReason: 'Cancelled by admin',
        );
    }
}
