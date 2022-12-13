<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum FilesystemKey: string implements EnumInterface
{
    use BaseEnumTrait;

    case Image = 'image';
    case Audio = 'audio';
    case Video = 'video';
    case Document = 'document';
    case Chunk = 'chunk';
    case Crop = 'crop';
}
