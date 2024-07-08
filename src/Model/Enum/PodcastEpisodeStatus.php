<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum PodcastEpisodeStatus: string implements EnumInterface
{
    use BaseEnumTrait;

    case NotImported = 'not_imported';
    case Imported = 'imported';
    case ImportFailed = 'import_failed';
    case Conflict = 'conflict';

    public const PodcastEpisodeStatus Default = self::NotImported;
}
