<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum AuthorCleanPhraseMode: string implements EnumInterface
{
    use BaseEnumTrait;

    case Remove = 'remove';
    case Replace = 'replace';

    public const AuthorCleanPhraseMode Default = self::Remove;
}
