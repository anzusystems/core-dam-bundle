<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum DistributionProcessStatus: string implements EnumInterface
{
    use BaseEnumTrait;

    public const string WAITING = 'waiting';
    public const string DISTRIBUTING = 'distributing';
    public const string REMOTE_PROCESSING = 'remote_processing';
    public const string DISTRIBUTED = 'distributed';
    public const string FAILED = 'failed';

    public const array FINISHED_MAP = [
        self::DISTRIBUTED,
        self::FAILED
    ];

    public const array NOT_FINISHED_MAP = [
        self::WAITING,
        self::DISTRIBUTING,
        self::REMOTE_PROCESSING
    ];

    case Waiting = self::WAITING;
    case Distributing = self::DISTRIBUTING;
    case RemoteProcessing = self::REMOTE_PROCESSING;
    case Distributed = self::DISTRIBUTED;
    case Failed = self::FAILED;

    public const DistributionProcessStatus Default = self::Waiting;
}
