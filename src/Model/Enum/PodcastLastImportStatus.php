<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum PodcastLastImportStatus: string implements EnumInterface
{
    use BaseEnumTrait;

    case notImported = 'not_imported';
    case imported = 'imported';
    case importFailed = 'import_failed';

    public const Default = self::notImported;
}
