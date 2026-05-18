<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Message;

/**
 * Messenger message for TTS audio narration jobs.
 * Only carries IDs and primitives — no entity references (anti-pattern for async messaging).
 */
final readonly class JobAudioNarrationMessage
{
    public function __construct(
        public string $jobId,
        public string $mode,
    ) {
    }
}
