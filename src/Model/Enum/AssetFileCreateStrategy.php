<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum AssetFileCreateStrategy: string implements EnumInterface
{
    use BaseEnumTrait;

    case Chunk = 'chunk';
    case ExternalProvider = 'external_provider';
    case Download = 'download';
    case Storage = 'storage';

    public const AssetFileCreateStrategy Default = self::Chunk;
}
