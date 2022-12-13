<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum DistributionRequirementStrategy: string implements EnumInterface
{
    use BaseEnumTrait;

    case none = 'none';
    case atLeastOne = 'at_least_one';
    case waitForAll = 'wait_for_all';

    public const Default = self::none;
}
