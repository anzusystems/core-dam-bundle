<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exception;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use Exception;

class DuplicateAssetFileException extends Exception
{
    private const string ERROR_MESSAGE = 'duplicate_asset_exception';

    private AssetFile $newAsset;
    private AssetFile $oldAsset;

    public function __construct(AssetFile $oldAsset, AssetFile $newAsset)
    {
        parent::__construct(self::ERROR_MESSAGE);
        $this->newAsset = $newAsset;
        $this->oldAsset = $oldAsset;
    }

    public function getOldAsset(): AssetFile
    {
        return $this->oldAsset;
    }

    public function getNewAsset(): AssetFile
    {
        return $this->newAsset;
    }
}
