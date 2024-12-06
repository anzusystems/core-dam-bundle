<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum AuthorCleanPhraseType: string implements EnumInterface
{
    use BaseEnumTrait;

    case Word = 'word';
    case Regex = 'regex';

    public const AuthorCleanPhraseType Default = self::Word;
}
