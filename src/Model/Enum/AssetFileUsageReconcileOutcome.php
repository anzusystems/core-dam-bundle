<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

/** AssetFileUsageReconciler::settleGroup() outcome; only counted, never persisted. */
enum AssetFileUsageReconcileOutcome: string implements EnumInterface
{
    use BaseEnumTrait;

    case Confirmed = 'confirmed';
    case Rewritten = 'rewritten';
    case Released = 'released';
    case Unanswered = 'unanswered';
    case Skipped = 'skipped';
}
