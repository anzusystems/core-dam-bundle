<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio;

/**
 * Result of finalizing a regenerate-cancel inside its transaction: whether the cancel succeeded
 * plus the optional post-commit callback data. Lets the cancellation flow return both values
 * instead of populating a by-reference array.
 */
final readonly class RegenCancelOutcome
{
    public function __construct(
        public bool $cancelled,
        public ?CancelledCallbackData $callbackData,
    ) {
    }
}
