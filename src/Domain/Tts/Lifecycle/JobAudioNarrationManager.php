<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle;

use AnzuSystems\CommonBundle\Model\Enum\JobStatus;
use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\JobAudioNarration;
use DateTimeImmutable;

/**
 * `openInitialKey` is cleared on every terminal transition — enforced here so callers can't forget.
 */
final class JobAudioNarrationManager extends AbstractManager
{
    public function create(JobAudioNarration $job, bool $flush = true): JobAudioNarration
    {
        $this->trackCreation($job);
        $this->entityManager->persist($job);
        $this->flush($flush);

        return $job;
    }

    public function markProcessing(JobAudioNarration $job, bool $flush = true): JobAudioNarration
    {
        $job->setStatus(JobStatus::Processing);
        $job->setStartedAt(new DateTimeImmutable());
        $this->flush($flush);

        return $job;
    }

    public function markCompleted(JobAudioNarration $job, bool $flush = true): JobAudioNarration
    {
        $job->setStatus(JobStatus::Done);
        $job->setFinishedAt(new DateTimeImmutable());
        $job->setOpenInitialKey(null);
        $this->flush($flush);

        return $job;
    }

    public function markFailed(JobAudioNarration $job, string $reason, bool $flush = true): JobAudioNarration
    {
        $job->setStatus(JobStatus::Error);
        $job->setFinishedAt(new DateTimeImmutable());
        $job->setFailureReason($reason);
        $job->setOpenInitialKey(null);
        $this->flush($flush);

        return $job;
    }

    public function markCancellationRequested(JobAudioNarration $job, bool $flush = false): JobAudioNarration
    {
        $job->setCancelRequested(true);
        $this->flush($flush);

        return $job;
    }

    /**
     * Releases the UNIQUE idempotency slot without touching status — cancel-regen finalize uses this
     * so a fresh initial dispatch for the same (extResourceName, extId) can proceed.
     */
    public function releaseIdempotencyKey(JobAudioNarration $job, bool $flush = false): JobAudioNarration
    {
        $job->setOpenInitialKey(null);
        $this->flush($flush);

        return $job;
    }

    public function markInitialCancelled(JobAudioNarration $job, bool $flush = false): JobAudioNarration
    {
        $job->setStatus(JobStatus::Error);
        $job->setCancelRequested(true);
        $job->setOpenInitialKey(null);
        $this->flush($flush);

        return $job;
    }
}
