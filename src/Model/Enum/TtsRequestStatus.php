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
    case Assembling = 'assembling';
    case Done = 'done';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public const TtsRequestStatus Default = self::Waiting;

    /**
     * Statuses that can still be cancelled (haven't reached a terminal state). Assembling is
     * intentionally OUT — once chunks are done and ffmpeg concat is in flight, cancellation
     * is impossible without leaking the partial Asset; cancel flow blocks at Processing latest.
     */
    public const array CANCELLABLE_STATUSES = [self::Waiting, self::Processing];
}
