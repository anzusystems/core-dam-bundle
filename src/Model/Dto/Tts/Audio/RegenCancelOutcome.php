<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio;

/** Result of a regenerate-cancel transaction: success flag + optional post-commit callback data. */
final readonly class RegenCancelOutcome
{
    public function __construct(
        public bool $cancelled,
        public ?CancelledCallbackData $callbackData,
    ) {
    }
}
