<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Command;

use AnzuSystems\CommonBundle\Model\Enum\JobStatus;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\JobAudioNarration;
use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
use AnzuSystems\CoreDamBundle\Exception\ImmutableAudioNarrationException;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\JobAudioNarrationManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetLocker;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsLifecycle;
use AnzuSystems\CoreDamBundle\Logger\TtsAuditLogger;
use AnzuSystems\CoreDamBundle\Repository\JobAudioNarrationRepository;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\CancelJobResponseDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\CancelJobStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsAudioStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsJobMode;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Two-phase regen cancel: phase 1 flips superseding→cancelling + signals worker, phase 2 re-locks
 * after commit and resolves the race. Initial cancel mutates only the Job (no asset exists yet).
 */
final readonly class CancelJob
{
    private const array CANCELLABLE_STATUSES = [JobStatus::Waiting, JobStatus::Processing];

    public function __construct(
        private TtsAssetLocker $assetLocker,
        private JobAudioNarrationRepository $jobRepo,
        private JobAudioNarrationManager $jobManager,
        private TtsAssetManager $ttsAssetManager,
        private TtsAuditLogger $auditLogger,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws ImmutableAudioNarrationException if job is missing or not cancellable
     */
    public function execute(string $jobId, ?string $reason, ?string $userId): CancelJobResponseDto
    {
        App::throwOnReadOnlyMode();

        $job = $this->jobRepo->find($jobId);
        if (null === $job) {
            throw new ImmutableAudioNarrationException(sprintf('Job "%s" not found.', $jobId));
        }
        if (false === $job->getStatus()->in(self::CANCELLABLE_STATUSES)) {
            throw new ImmutableAudioNarrationException(sprintf(
                'Job "%s" cannot be cancelled in status "%s".',
                $jobId,
                $job->getStatus()->value,
            ));
        }

        return match ($job->getMode()) {
            TtsJobMode::Initial    => $this->cancelInitial($job, $reason, $userId),
            TtsJobMode::Regenerate => $this->cancelRegenerate((string) $job->getStableAssetId(), $reason ?? App::EMPTY_STRING, $userId),
        };
    }

    private function cancelInitial(JobAudioNarration $job, ?string $reason, ?string $userId): CancelJobResponseDto
    {
        $this->entityManager->wrapInTransaction(function () use ($job, $reason, $userId): void {
            // Re-lock for status consistency under concurrency.
            $locked = $this->jobRepo->find((string) $job->getId(), LockMode::PESSIMISTIC_WRITE);
            if (null === $locked || false === $locked->getStatus()->in(self::CANCELLABLE_STATUSES)) {
                throw new ImmutableAudioNarrationException(sprintf('Job "%s" no longer cancellable.', (string) $job->getId()));
            }

            $this->jobManager->markInitialCancelled($locked);
            $this->auditLogger->logInitialCancelled((string) $locked->getId(), $userId, $reason);

            $this->entityManager->flush();
        });

        return CancelJobResponseDto::getInstance(CancelJobStatus::Cancelled, false);
    }

    private function cancelRegenerate(string $stableAssetId, string $reason, ?string $userId): CancelJobResponseDto
    {
        $this->entityManager->wrapInTransaction(fn () => $this->requestStop($stableAssetId));

        return $this->entityManager->wrapInTransaction(fn () => $this->finalizeRegen($stableAssetId, $reason, $userId));
    }

    private function requestStop(string $stableAssetId): void
    {
        $ttsAsset = $this->assetLocker->lockExpecting($stableAssetId, TtsLifecycle::SUPERSEDING_ONLY);

        $this->ttsAssetManager->markCancelling($ttsAsset);

        $regenJobId = $ttsAsset->getRegenJobId();
        if (null !== $regenJobId) {
            $job = $this->jobRepo->find($regenJobId);
            if (null !== $job) {
                $this->jobManager->markCancellationRequested($job);
            }
        }

        $this->entityManager->flush();
    }

    private function finalizeRegen(string $stableAssetId, string $reason, ?string $userId): CancelJobResponseDto
    {
        $ttsAsset = $this->assetLocker->lock($stableAssetId);

        return match ($ttsAsset->getStatus()) {
            TtsAudioStatus::Cancelling => $this->finalizeWonRace($ttsAsset, $reason, $userId),
            TtsAudioStatus::Active     => CancelJobResponseDto::getInstance(CancelJobStatus::SwapCompleted, true),
            default                    => CancelJobResponseDto::getInstance(CancelJobStatus::AlreadyFailed, false),
        };
    }

    private function finalizeWonRace(TtsAsset $ttsAsset, string $reason, ?string $userId): CancelJobResponseDto
    {
        $assetId = (string) $ttsAsset->getAsset()->getId();
        $regenJobId = $ttsAsset->getRegenJobId();

        $this->ttsAssetManager->markFailed($ttsAsset, $reason);

        if (null !== $regenJobId) {
            $job = $this->jobRepo->find($regenJobId);
            if (null !== $job && $job->getMode()->is(TtsJobMode::Initial)) {
                $this->jobManager->releaseIdempotencyKey($job);
            }
        }

        $this->auditLogger->logCancelled($assetId, $regenJobId, $userId, $reason);

        $this->entityManager->flush();

        return CancelJobResponseDto::getInstance(CancelJobStatus::Cancelled, false);
    }
}
