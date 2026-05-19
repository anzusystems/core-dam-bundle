<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Message;

final readonly class TtsNarrationRequestMessage
{
    public function __construct(
        public string $requestId,
    ) {
    }
}
