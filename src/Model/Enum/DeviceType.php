<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExportTypeEnableInterface;

enum DeviceType: string implements EnumInterface
{
    use BaseEnumTrait;

    case All = 'all';
    case Ios = 'ios';
    case Android = 'android';

    public const DeviceType Default = self::All;
}
