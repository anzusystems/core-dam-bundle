<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Enum;

/** Outcome of TtsRequestOrchestrator::resumeStalled(); drives reconcile command fail-vs-log decision. */
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
