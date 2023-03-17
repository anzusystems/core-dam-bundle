<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Asset;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\Asset;

final readonly class AssetTextsProcessor
{
    public function __construct(
        private ConfigurationProvider $configurationProvider,
        private AssetTextsWriter $assetTextsWriter,
    ) {
    }

    public function updateAssetDisplayTitle(Asset $asset): void
    {
        $asset->getTexts()->setDisplayTitle(
            $this->getAssetDisplayTitle($asset)
        );
    }

    public function getAssetDisplayTitle(Asset $asset): string
    {
        return (string) $this->assetTextsWriter->getFirstValue(
            from: $asset,
            config: $this->configurationProvider->getDisplayTitle()->getDisplayTitleConfig(
                $asset->getAttributes()->getAssetType()
            )
        );
    }
}
