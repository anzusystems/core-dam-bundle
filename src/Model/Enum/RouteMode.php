<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum RouteMode: string implements EnumInterface
{
    use BaseEnumTrait;

    case StorageCopy = 'storage_copy';
    case Direct = 'direct';

    public const RouteMode Default = self::Direct;
}
