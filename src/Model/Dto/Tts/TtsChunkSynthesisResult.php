<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts;

/** Single provider HTTP call output: MP3 bytes + ElevenLabs request-id (null for Google). */
final readonly class TtsChunkSynthesisResult
{
    public function __construct(
        public string $bytes,
        public ?string $requestId,
    ) {
    }
}
