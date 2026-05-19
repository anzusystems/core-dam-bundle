<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

/**
 * Resolution kind for cancel-request — surfaces only in {@see CancelRequestResponseDto}, never persisted.
 */
enum CancelRequestStatus: string implements EnumInterface
{
    use BaseEnumTrait;

    case Cancelled = 'cancelled';
    case SwapCompleted = 'swap_completed';
    case AlreadyFailed = 'already_failed';
}
