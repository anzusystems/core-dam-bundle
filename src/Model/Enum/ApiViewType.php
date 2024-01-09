<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum ApiViewType: string implements EnumInterface
{
    use BaseEnumTrait;

    public const LIST = 'list';
    public const DETAIL= 'detail';

    case List = self::LIST;
    case Detail = self::DETAIL;
}
