<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Message;

/**
 * Synthesise one chunk of a multi-chunk TTS request in its own worker run. The chunk handler dispatches
 * the next chunk's message after its own commit; the last chunk assembles inline.
 */
final readonly class TtsSynthChunkMessage
{
    public function __construct(
        public string $chunkId,
    ) {
    }
}
