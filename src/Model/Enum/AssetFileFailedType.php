<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum AssetFileFailedType: string implements EnumInterface
{
    use BaseEnumTrait;

    case None = 'none';
    case Unknown = 'unknown';
    case InvalidChecksum = 'invalid_checksum';
    case InvalidMimeType = 'invalid_mime_type';
    case DownloadFailed = 'download_failed';
    case InvalidSize = 'invalid_size';

    public const Default = self::None;
}
