<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exception;

use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileFailedType;
use Exception;

class AssetFileProcessFailed extends Exception
{
    public const string ERROR_MESSAGE = 'asset_file_process_failed';

    public function __construct(
        private readonly AssetFileFailedType $assetFileFailedType,
    ) {
        parent::__construct(self::ERROR_MESSAGE);
    }

    public function getAssetFileFailedType(): AssetFileFailedType
    {
        return $this->assetFileFailedType;
    }
}
