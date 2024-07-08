<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum AuthorType: string implements EnumInterface
{
    use BaseEnumTrait;

    case None = 'none';
    case Internal = 'internal';
    case External = 'external';
    case Agency = 'agency';

    public const AuthorType Default = self::None;
}
