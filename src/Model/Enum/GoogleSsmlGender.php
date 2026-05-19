<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum GoogleSsmlGender: string implements EnumInterface
{
    use BaseEnumTrait;

    case Male = 'MALE';
    case Female = 'FEMALE';
    case Neutral = 'NEUTRAL';

    public const GoogleSsmlGender Default = self::Neutral;
}
