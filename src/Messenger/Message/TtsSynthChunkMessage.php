<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Message;

/** One chunk synthesis run; handler dispatches the next chunk after commit, last chunk assembles inline. */
final readonly class TtsSynthChunkMessage
{
    public function __construct(
        public string $chunkId,
    ) {
    }
}
