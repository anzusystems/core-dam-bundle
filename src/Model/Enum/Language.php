<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;
use AnzuSystems\CoreDamBundle\Exception\InvalidArgumentException;

enum Language: string implements EnumInterface
{
    use BaseEnumTrait;

    case All = 'all';
    case Slovak = 'sk';
    case English = 'en';

    public function getLocale(): string
    {
        return match($this)
        {
            self::Slovak => 'sk_SK',
            self::English => 'en_US',
            default => throw new InvalidArgumentException('Missing locale')
        };
    }

    /** BCP-47 tag for Google TTS `languageCode` (ElevenLabs auto-detects from its model). */
    public function getBcpLocale(): string
    {
        return str_replace('_', '-', $this->getLocale());
    }
}
