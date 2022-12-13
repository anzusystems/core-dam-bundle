<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exception;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileFailedType;
use Exception;

class AssetFileProcessFailed extends Exception
{
    private const ERROR_MESSAGE = 'asset_contains_process_failed';

    public function __construct(
        private readonly AssetFile $assetFile,
        private readonly AssetFileFailedType $assetFileFailedType,
    ) {
        parent::__construct(self::ERROR_MESSAGE);
    }

    public function getAssetFile(): AssetFile
    {
        return $this->assetFile;
    }

    public function getAssetFileFailedType(): AssetFileFailedType
    {
        return $this->assetFileFailedType;
    }
}
