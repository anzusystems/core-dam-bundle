<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum JwMediaStatus: string implements EnumInterface
{
    use BaseEnumTrait;

    case Created = 'created';
    case Processing = 'processing';
    case Ready = 'ready';
    case Updating = 'updating';
    case Failed = 'failed';

    public const Default = self::Created;
}
