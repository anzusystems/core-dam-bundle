<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Voice;

use AnzuSystems\CoreDamBundle\Model\Enum\TtsProvider;

final readonly class ResolvedVoice
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $voiceFamilyId,
        public string $voiceFamilySlug,
        public TtsProvider $provider,
        public string $externalVoiceId,
        public array $metadata,
    ) {
    }
}
