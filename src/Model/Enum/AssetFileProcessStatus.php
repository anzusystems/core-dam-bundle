<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum AssetFileProcessStatus: string implements EnumInterface
{
    use BaseEnumTrait;

    case Uploading = 'uploading';       // file entity created and ready to receive chunks
    case Uploaded = 'uploaded';         // all chunks were sent
    case Stored = 'stored';             // File is stored and ready to processing
    case Duplicate = 'duplicate';       // AssetFile is duplicate of another asset
    case Processed = 'processed';       // file processed and ready to serve
    case Failed = 'failed';

    public const AssetFileProcessStatus Default = self::Uploading;
}
