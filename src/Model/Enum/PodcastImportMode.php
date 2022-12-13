<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum PodcastImportMode: string implements EnumInterface
{
    use BaseEnumTrait;

    case notImport = 'not_import';
    case import = 'import';

    public const Default = self::import;

    public static function getAllImportModes(): array
    {
        return [
            self::import
        ];
    }
}
