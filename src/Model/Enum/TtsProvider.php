<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum TtsProvider: string implements EnumInterface
{
    use BaseEnumTrait;

    case Elevenlabs = 'elevenlabs';
    case GoogleTts = 'google_tts';

    public const TtsProvider Default = self::Elevenlabs;
}
