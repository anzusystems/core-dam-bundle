<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;

final readonly class AssetFileDuplicatePreFlushEvent
{
    public function __construct(
        private AssetFile $assetFile,
        private AssetFile $originAssetFile,
    ) {
    }

    public function getAssetFile(): AssetFile
    {
        return $this->assetFile;
    }

    public function getOriginAssetFile(): AssetFile
    {
        return $this->originAssetFile;
    }
}
