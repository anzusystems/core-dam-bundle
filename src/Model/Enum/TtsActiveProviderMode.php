<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

/** Per-ExtSystem TTS provider strategy; forced modes reject dispatch if no matching voice exists. */
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
