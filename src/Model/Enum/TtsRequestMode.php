<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum TtsRequestMode: string implements EnumInterface
{
    use BaseEnumTrait;

    case Initial = 'initial';
    case Regenerate = 'regenerate';

    public const TtsRequestMode Default = self::Initial;
}
