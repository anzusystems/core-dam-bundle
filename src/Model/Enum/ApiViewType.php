<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum ApiViewType: string implements EnumInterface
{
    use BaseEnumTrait;

    case List = 'list';
    case Detail = 'detail';
}
