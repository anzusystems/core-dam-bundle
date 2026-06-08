<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum TtsRequestStatus: string implements EnumInterface
{
    use BaseEnumTrait;

    case Waiting = 'waiting';
    case Processing = 'processing';
    case Done = 'done';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public const TtsRequestStatus Default = self::Waiting;

    /** Statuses eligible for cancellation (cancel blocks at Processing at the latest). */
    public const array CANCELLABLE_STATUSES = [self::Waiting, self::Processing];

    /** Terminal statuses — no further transitions allowed. */
    public const array TERMINAL_STATUSES = [self::Done, self::Failed, self::Cancelled];
}
