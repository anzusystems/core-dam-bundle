<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum DistributionRequirementStrategy: string implements EnumInterface
{
    use BaseEnumTrait;

    case None = 'none';
    case AtLeastOne = 'at_least_one';
    case WaitForAll = 'wait_for_all';

    public const DistributionRequirementStrategy Default = self::None;
}
