<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;

abstract class AbstractAssetFileEvent
{
    public function __construct(
        protected readonly AssetFile $asset
    ) {
    }

    public function getAsset(): AssetFile
    {
        return $this->asset;
    }
}
