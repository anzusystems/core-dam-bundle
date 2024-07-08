<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum DistributionFailReason: string implements EnumInterface
{
    use BaseEnumTrait;

    case Unknown = 'unknown';
    case None = 'none';
    case QuotaReached = 'quota_reached';
    case RemoteProcessFailed = 'remote_process_failed';
    case ValidationFailed = 'validation_failed';

    public const DistributionFailReason Default = self::None;
}
