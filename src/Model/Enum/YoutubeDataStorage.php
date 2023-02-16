<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum YoutubeDataStorage: string implements EnumInterface
{
    use BaseEnumTrait;

    case Playlist = 'playlist';
    case Language = 'language';
}
