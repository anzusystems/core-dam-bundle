<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum ExtSystemSourcesRequired: string implements EnumInterface
{
    use BaseEnumTrait;

    case Never = 'never';
    case Adm = 'adm';

    public const self Default = self::Never;
}
