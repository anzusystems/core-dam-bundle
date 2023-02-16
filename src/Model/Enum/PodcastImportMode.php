<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum PodcastImportMode: string implements EnumInterface
{
    use BaseEnumTrait;

    case NotImport = 'not_import';
    case Import = 'import';

    public const Default = self::Import;

    public static function getAllImportModes(): array
    {
        return [
            self::Import
        ];
    }
}
