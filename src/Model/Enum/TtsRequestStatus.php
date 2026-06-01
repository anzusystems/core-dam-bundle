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

    /**
     * Statuses that can still be cancelled (haven't reached a terminal state). Synthesis runs in the
     * Processing window (provider HTTP + in-memory chunking + ffmpeg concat), so the cancel flow
     * blocks at Processing at the latest.
     */
    public const array CANCELLABLE_STATUSES = [self::Waiting, self::Processing];

    /**
     * Terminal statuses — a request that has reached any of these must never transition again.
     * Guards against a post-completion pipeline error flipping a {@see self::Done} request to
     * {@see self::Failed} (which would trigger a spurious failure callback to the ext-system).
     */
    public const array TERMINAL_STATUSES = [self::Done, self::Failed, self::Cancelled];
}
