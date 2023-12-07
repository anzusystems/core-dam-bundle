<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum RouteStatus: string implements EnumInterface
{
    use BaseEnumTrait;

    case Disabled = 'disabled';
    case Active = 'active';

    public const Default = self::Disabled;
}
