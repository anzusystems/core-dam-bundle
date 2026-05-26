<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Command;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetLocker;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsLifecycle;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsNarrationRequestManager;
use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Exception\ImmutableAudioNarrationException;
use AnzuSystems\CoreDamBundle\Logger\TtsAuditLogger;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\CancelRequestResponseDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\CancelRequestStatus;
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
final readonly class CancelRequest
{
    public function __construct(
        private TtsAssetLocker $assetLocker,
        private TtsNarrationRequestRepository $requestRepo,
        private TtsNarrationRequestManager $requestManager,
        private TtsAssetManager $ttsAssetManager,
        private TtsAuditLogger $auditLogger,
        private EntityManagerInterface $entityManager,
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
        $this->entityManager->wrapInTransaction(function () use ($request, $reason, $userId): void {
            // Re-lock for status consistency under concurrency.
            $locked = $this->requestRepo->find((string) $request->getId(), LockMode::PESSIMISTIC_WRITE);
            if (null === $locked || false === $locked->getStatus()->in(TtsRequestStatus::CANCELLABLE_STATUSES)) {
                throw new ImmutableAudioNarrationException(sprintf('Request "%s" no longer cancellable.', (string) $request->getId()));
            }

            $this->requestManager->markCancelled($locked, $reason);
            $this->auditLogger->logInitialCancelled((string) $locked->getId(), $userId, $reason);

            $this->entityManager->flush();
        });

        return CancelRequestResponseDto::getInstance(CancelRequestStatus::Cancelled, false);
    }

    private function cancelRegenerate(string $stableAssetId, string $reason, ?string $userId): CancelRequestResponseDto
    {
        $this->entityManager->wrapInTransaction(fn () => $this->requestStop($stableAssetId));

        return $this->entityManager->wrapInTransaction(fn () => $this->finalizeRegen($stableAssetId, $reason, $userId));
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

    private function finalizeRegen(string $stableAssetId, string $reason, ?string $userId): CancelRequestResponseDto
    {
        $ttsAsset = $this->assetLocker->lock($stableAssetId);

        return match ($ttsAsset->getStatus()) {
            TtsAudioStatus::Cancelling => $this->finalizeWonRace($ttsAsset, $reason, $userId),
            TtsAudioStatus::Active => CancelRequestResponseDto::getInstance(CancelRequestStatus::SwapCompleted, true),
            default => CancelRequestResponseDto::getInstance(CancelRequestStatus::AlreadyFailed, false),
        };
    }

    private function finalizeWonRace(TtsAsset $ttsAsset, string $reason, ?string $userId): CancelRequestResponseDto
    {
        $assetId = (string) $ttsAsset->getAsset()->getId();
        $activeRegen = $this->requestRepo->findActiveRegenForStable($assetId);

        $this->ttsAssetManager->markFailed($ttsAsset, $reason);

        if (null !== $activeRegen) {
            $this->requestManager->markCancelled($activeRegen, $reason);
        }

        $this->auditLogger->logCancelled($assetId, (string) $activeRegen?->getId(), $userId, $reason);

        $this->entityManager->flush();

        return CancelRequestResponseDto::getInstance(CancelRequestStatus::Cancelled, false);
    }
}
