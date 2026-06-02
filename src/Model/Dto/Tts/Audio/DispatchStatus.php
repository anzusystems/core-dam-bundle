<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

/**
 * Outcome status for {@see \AnzuSystems\CoreDamBundle\Domain\Tts\Facade\TtsDispatchFacade}.
 * Surfaces in {@see DispatchResult} and in the API response payload — never persisted.
 */
enum DispatchStatus: string implements EnumInterface
{
    use BaseEnumTrait;

    case Pending = 'pending';
    case AlreadyExists = 'already_exists';
    case AlreadyPending = 'already_pending';
    case Duplicate = 'duplicate';
}
