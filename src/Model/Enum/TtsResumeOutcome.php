<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Enum;

/**
 * Outcome of {@see \AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline\TtsRequestOrchestrator::resumeStalled()} —
 * drives the reconcile command's fail-vs-log decision and the cron output label.
 */
enum TtsResumeOutcome: string
{
    case Skipped = 'skip';
    case Redispatched = 'redispatched';
    case Finalized = 'finalized';
    case HasFailed = 'has-failed';
    case NoChunks = 'no-chunks';

    public function isUnrecoverable(): bool
    {
        return self::HasFailed === $this || self::NoChunks === $this;
    }
}
