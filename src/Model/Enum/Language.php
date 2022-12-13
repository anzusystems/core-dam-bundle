<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;
use AnzuSystems\CoreDamBundle\Exception\InvalidArgumentException;

enum Language: string implements EnumInterface
{
    use BaseEnumTrait;

    case All = 'all';
    case Slovak = 'sk';

    public function getLocale(): string
    {
        return match($this)
        {
            self::Slovak => 'sk_SK',
            default => throw new InvalidArgumentException('Missing locale')
        };
    }
}
