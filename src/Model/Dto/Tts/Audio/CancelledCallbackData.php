<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio;

/** Post-commit data for the "cancelled" media-status callback; returned out of the transaction. */
final readonly class CancelledCallbackData
{
    public function __construct(
        public int $extSystemId,
        public string $assetId,
    ) {
    }
}
