<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

/**
 * Per-ExtSystem TTS provider strategy.
 *
 *  - Elevenlabs / GoogleTts: forced — the resolver MUST pick this provider's voice for the
 *    requested VoiceFamily; if the family has no voice for the forced provider, the dispatch is
 *    rejected with a validation error (no silent fallback).
 *  - Auto: resolver uses {@see VoiceFamily::preferredProvider} as primary, then falls back to the
 *    family's main voice. Original cascade.
 */
enum TtsActiveProviderMode: string implements EnumInterface
{
    use BaseEnumTrait;

    case Elevenlabs = 'elevenlabs';
    case GoogleTts = 'google_tts';
    case Auto = 'auto';

    public const TtsActiveProviderMode Default = self::Auto;

    public function toProvider(): ?VoiceDiscriminator
    {
        return match ($this) {
            self::Elevenlabs => VoiceDiscriminator::Elevenlabs,
            self::GoogleTts => VoiceDiscriminator::GoogleTts,
            self::Auto => null,
        };
    }
}
