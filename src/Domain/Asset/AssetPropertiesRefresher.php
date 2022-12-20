<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Asset;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;

class AssetPropertiesRefresher extends AbstractManager
{
    public function __construct(
        private readonly AssetTextsProcessor $assetTextsProcessor
    ) {
    }

    /**
     * Used for refresh RO properties e.g. display title, main file, ...
     */
    public function refreshProperties(Asset $asset): Asset
    {
        $this->assetTextsProcessor->updateAssetDisplayTitle($asset);

        return $asset;
    }
}
