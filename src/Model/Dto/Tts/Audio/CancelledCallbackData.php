<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio;

/**
 * Data needed to fire the post-commit "cancelled" media-status callback to the ext-system. Returned out of the
 * cancellation transaction (instead of mutating a by-ref array) so the callback can be dispatched after commit.
 */
final readonly class CancelledCallbackData
{
    public function __construct(
        public int $extSystemId,
        public string $assetId,
    ) {
    }
}
