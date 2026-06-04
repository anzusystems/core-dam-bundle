<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts;

/**
 * One provider HTTP call's output: raw MP3 bytes + the provider request-id (ElevenLabs only; null for
 * the stateless Google provider). The request-id feeds the next chunk's `previous_request_ids` chain.
 */
final readonly class TtsChunkSynthesisResult
{
    public function __construct(
        public string $bytes,
        public ?string $requestId,
    ) {
    }
}
