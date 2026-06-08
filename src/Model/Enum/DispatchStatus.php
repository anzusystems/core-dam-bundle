<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

/** TtsDispatchFacade outcome; surfaces in DispatchResult and API response, never persisted. */
enum DispatchStatus: string implements EnumInterface
{
    use BaseEnumTrait;

    case Pending = 'pending';
    case AlreadyPending = 'already_pending';
    case Duplicate = 'duplicate';
}
