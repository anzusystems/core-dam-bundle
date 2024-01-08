<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum CustomFormElementType: string implements EnumInterface
{
    use BaseEnumTrait;

    case String = 'string';
    case Integer = 'integer';
    case StringArray = 'string_array';
    case Boolean = 'boolean';
    case Choice = 'choice';

    public const Default = self::String;

    public function allowedSearch(): bool
    {
        return match($this) {
            self::Integer, self::String, self::Boolean  => true,
            self::Choice, self::StringArray => false,
        };
    }
}
