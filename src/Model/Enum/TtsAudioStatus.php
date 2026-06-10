<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum TtsAudioStatus: string implements EnumInterface
{
    use BaseEnumTrait;

    case Active = 'active';
    case Superseding = 'superseding';
    case Cancelling = 'cancelling';
    case Failed = 'failed';
    case Unpublished = 'unpublished';

    public const TtsAudioStatus Default = self::Active;
}
