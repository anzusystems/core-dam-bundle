<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum DistributionStrategy: string implements EnumInterface
{
    use BaseEnumTrait;

    public const array CHOICES = [
        self::NONE,
        self::AT_LEAST_ONE,
        self::ONE_FROM_TYPE,
        self::WAIT_FOR_ALL,
    ];

    public const string NONE = 'none';
    public const string AT_LEAST_ONE = 'at_least_one';
    public const string ONE_FROM_TYPE = 'one_from_type';
    public const string WAIT_FOR_ALL = 'wait_for_all';

    case None = self::NONE;
    case AtLeastOne = self::AT_LEAST_ONE;
    case OneFromType = self::ONE_FROM_TYPE;
    case WaitForAll = self::WAIT_FOR_ALL;
}
