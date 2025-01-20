<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum ExportType: string implements EnumInterface
{
    use BaseEnumTrait;


    case Web = 'web';
    case Mobile = 'mobile';

    public const ExportType Default = self::Mobile;
}
