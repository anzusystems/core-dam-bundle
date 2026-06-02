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
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\CancelRequestResponseDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\CancelRequestStatus;
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
     * @throws ImmutableAudioNarrationException if request is not cancellable
     */
    public function execute(TtsNarrationRequest $request, ?string $reason, ?string $userId): CancelRequestResponseDto
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
            TtsRequestMode::Initial => $this->cancelInitial($request, $reason, $userId),
            TtsRequestMode::Regenerate => $this->cancelRegenerate((string) $request->getStableAssetId(), $reason ?? App::EMPTY_STRING, $userId),
        };
    }

    private function cancelInitial(TtsNarrationRequest $request, ?string $reason, ?string $userId): CancelRequestResponseDto
    {
        $callbackData = $this->entityManager->wrapInTransaction(
            function () use ($request, $reason, $userId): ?CancelledCallbackData {
                // Re-lock for status consistency under concurrency.
                $locked = $this->requestRepo->find((string) $request->getId(), LockMode::PESSIMISTIC_WRITE);
                if (null === $locked || false === $locked->getStatus()->in(TtsRequestStatus::CANCELLABLE_STATUSES)) {
                    throw new ImmutableAudioNarrationException(sprintf('Request "%s" no longer cancellable.', (string) $request->getId()));
                }

                $this->requestManager->markCancelled($locked, $reason);
                $this->auditLogger->logInitialCancelled((string) $locked->getId(), $userId, $reason);

                $callbackData = $this->buildCallbackData($locked, (string) $locked->getStableAssetId());

                $this->entityManager->flush();

                return $callbackData;
            }
        );

        if (null !== $callbackData) {
            $this->dispatchCancelledCallback($callbackData, $reason ?? App::EMPTY_STRING);
        }

        return CancelRequestResponseDto::getInstance(CancelRequestStatus::Cancelled, false);
    }

    private function cancelRegenerate(string $stableAssetId, string $reason, ?string $userId): CancelRequestResponseDto
    {
        $this->entityManager->wrapInTransaction(fn () => $this->requestStop($stableAssetId));

        $outcome = $this->entityManager->wrapInTransaction(
            fn (): RegenCancelOutcome => $this->finalizeRegen($stableAssetId, $reason, $userId)
        );

        if (null !== $outcome->callbackData) {
            $this->dispatchCancelledCallback($outcome->callbackData, $reason);
        }

        return $outcome->response;
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
        string $reason,
        ?string $userId,
    ): RegenCancelOutcome {
        $ttsAsset = $this->assetLocker->lock($stableAssetId);

        return match ($ttsAsset->getStatus()) {
            TtsAudioStatus::Cancelling => $this->finalizeWonRace($ttsAsset, $reason, $userId),
            TtsAudioStatus::Active => new RegenCancelOutcome(CancelRequestResponseDto::getInstance(CancelRequestStatus::SwapCompleted, true), null),
            default => new RegenCancelOutcome(CancelRequestResponseDto::getInstance(CancelRequestStatus::AlreadyFailed, false), null),
        };
    }

    private function finalizeWonRace(
        TtsAsset $ttsAsset,
        string $reason,
        ?string $userId,
    ): RegenCancelOutcome {
        $assetId = (string) $ttsAsset->getAsset()->getId();
        $activeRegen = $this->requestRepo->findActiveRegenForStable($assetId);

        // The swap never ran, so the old audio is intact — return the asset to Active, not Failed.
        $this->ttsAssetManager->markActive($ttsAsset);

        $callbackData = null;
        if (null !== $activeRegen) {
            $this->requestManager->markCancelled($activeRegen, $reason);
            $callbackData = $this->buildCallbackData($activeRegen, $assetId);
        }

        $this->auditLogger->logCancelled($assetId, (string) $activeRegen?->getId(), $userId, $reason);

        $this->entityManager->flush();

        return new RegenCancelOutcome(CancelRequestResponseDto::getInstance(CancelRequestStatus::Cancelled, false), $callbackData);
    }

    private function buildCallbackData(TtsNarrationRequest $request, string $assetId): ?CancelledCallbackData
    {
        $extResourceName = $request->getExtRef()->getExtResourceName();
        $extId = $request->getExtRef()->getExtId();
        if (null === $extResourceName || null === $extId) {
            return null;
        }

        return new CancelledCallbackData($request->getExtSystemId(), $extResourceName, $extId, $assetId);
    }

    private function dispatchCancelledCallback(CancelledCallbackData $callbackData, string $reason): void
    {
        $this->extSystemCallbackFacade->notifyMediaStatusBestEffort(
            extSystemId: $callbackData->extSystemId,
            extResourceName: $callbackData->extResourceName,
            extId: $callbackData->extId,
            assetId: $callbackData->assetId,
            status: MediaStatusType::GenerationFailed,
            failureReason: sprintf('Cancelled by admin: %s', $reason),
        );
    }
}
