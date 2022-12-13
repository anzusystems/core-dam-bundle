<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum DistributionStrategy: string implements EnumInterface
{
    use BaseEnumTrait;

    public const CHOICES = [
        self::NONE,
        self::AT_LEAST_ONE,
        self::ONE_FROM_TYPE,
        self::WAIT_FOR_ALL,
    ];

    public const NONE = 'none';
    public const AT_LEAST_ONE = 'at_least_one';
    public const ONE_FROM_TYPE = 'one_from_type';
    public const WAIT_FOR_ALL = 'wait_for_all';

    case none = self::NONE;
    case atLeastOne = self::AT_LEAST_ONE;
    case oneFromType = self::ONE_FROM_TYPE;
    case waitForAll = self::WAIT_FOR_ALL;
}
