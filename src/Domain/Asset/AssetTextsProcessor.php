<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Asset;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use Doctrine\ORM\NonUniqueResultException;

final class AssetTextsProcessor
{
    public function __construct(
        private readonly ConfigurationProvider $configurationProvider,
        private readonly AssetPropertyAccessor $accessor,
    ) {
    }

    /**
     * @throws NonUniqueResultException
     */
    public function updateAssetDisplayTitle(Asset $asset): void
    {
        $asset->getTexts()->setDisplayTitle(
            $this->getAssetDisplayTitle($asset)
        );
    }

    /**
     * @throws NonUniqueResultException
     */
    public function getAssetDisplayTitle(Asset $asset): string
    {
        return $this->accessor->getPropertyValue(
            $asset,
            $this->getDisplayTitleConfig($asset)
        );
    }

    private function getDisplayTitleConfig(Asset $asset): array
    {
        $config = $this->configurationProvider->getDisplayTitle();

        return match ($asset->getAttributes()->getAssetType()) {
            AssetType::Image => $config->getImage(),
            AssetType::Video => $config->getVideo(),
            AssetType::Audio => $config->getAudio(),
            AssetType::Document => $config->getDocument(),
        };
    }
}
