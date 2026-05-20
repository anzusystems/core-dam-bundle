<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

/**
 * Per-chunk lifecycle for {@see \AnzuSystems\CoreDamBundle\Entity\TtsSynthesisChunk}.
 *
 *   Pending    — created at dispatch, waiting for a worker to claim
 *   Processing — claimed by a worker, synth in progress
 *   Done       — MP3 bytes persisted to the per-extSystem chunk storage, ready for assembly
 *   Failed     — terminal; cron sweeper may not retry. Causes parent request to fail at assemble time.
 */
enum TtsChunkStatus: string implements EnumInterface
{
    use BaseEnumTrait;

    case Pending = 'pending';
    case Processing = 'processing';
    case Done = 'done';
    case Failed = 'failed';

    public const TtsChunkStatus Default = self::Pending;
}
