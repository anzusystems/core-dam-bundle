<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum AssetFileCopyStatus: string implements EnumInterface
{
    use BaseEnumTrait;

    case Exists = 'exists';
    case Copy = 'copy';
    case NotAllowed = 'notAllowed';
    case Unassigned = 'unassigned';

    public const self Default = self::Unassigned;
}
