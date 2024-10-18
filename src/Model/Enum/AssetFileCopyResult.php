<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum AssetFileCopyResult: string implements EnumInterface
{
    use BaseEnumTrait;

    case Exists = 'exists';
    case Copying = 'copying';
    case NotAllowed = 'notAllowed';

    public const self Default = self::NotAllowed;
}
