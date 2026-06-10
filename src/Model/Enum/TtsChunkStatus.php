<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

/** Per-chunk lifecycle; Failed is terminal and propagates to the parent request. */
enum TtsChunkStatus: string implements EnumInterface
{
    use BaseEnumTrait;

    case Pending = 'pending';
    case Processing = 'processing';
    case Done = 'done';
    case Failed = 'failed';

    public const TtsChunkStatus Default = self::Pending;
}
