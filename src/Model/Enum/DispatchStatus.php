<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

/**
 * Outcome status for {@see \AnzuSystems\CoreDamBundle\Domain\Tts\Facade\TtsDispatchFacade}.
 * Surfaces in {@see \AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\DispatchResult} and in the API
 * response payload — never persisted.
 */
enum DispatchStatus: string implements EnumInterface
{
    use BaseEnumTrait;

    case Pending = 'pending';
    case AlreadyPending = 'already_pending';
    case Duplicate = 'duplicate';
}
