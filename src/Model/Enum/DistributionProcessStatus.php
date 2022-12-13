<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum DistributionProcessStatus: string implements EnumInterface
{
    use BaseEnumTrait;

    public const WAITING = 'waiting';
    public const DISTRIBUTING = 'distributing';
    public const REMOTE_PROCESSING = 'remote_processing';
    public const DISTRIBUTED = 'distributed';
    public const FAILED = 'failed';

    public const FINISHED_MAP = [
        self::DISTRIBUTED,
        self::FAILED
    ];

    public const NOT_FINISHED_MAP = [
        self::WAITING,
        self::DISTRIBUTING,
        self::REMOTE_PROCESSING
    ];

    case Waiting = self::WAITING;
    case Distributing = self::DISTRIBUTING;
    case RemoteProcessing = self::REMOTE_PROCESSING;
    case Distributed = self::DISTRIBUTED;
    case Failed = self::FAILED;

    public const Default = self::Waiting;
}
