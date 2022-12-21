<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Asset;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetHasFile;
use Doctrine\ORM\NonUniqueResultException;

class AssetPropertiesRefresher extends AbstractManager
{
    public function __construct(
        private readonly AssetTextsProcessor $assetTextsProcessor
    ) {
    }

    /**
     * Used for refresh RO properties e.g. display title, main file, ...
     *
     * @throws NonUniqueResultException
     */
    public function refreshProperties(Asset $asset): Asset
    {
        $this->assetTextsProcessor->updateAssetDisplayTitle($asset);
        $this->refreshMainFile($asset);

        return $asset;
    }

    private function refreshMainFile(Asset $asset): void
    {
        $manFileSlot = $this->getMainFileSlot($asset);
        if ($manFileSlot instanceof AssetHasFile) {
            return;
        }

        $newMainFileSlot = $this->getDefaultSlot($asset) ?? $asset->getFiles()->first();
        if ($newMainFileSlot instanceof AssetHasFile) {
            $asset->setMainFile($newMainFileSlot->getAssetFile());

            return;
        }

        $asset->setMainFile(null);
    }

    private function getMainFileSlot(Asset $asset): ?AssetHasFile
    {
        if (null === $asset->getMainFile()) {
            return null;
        }

        foreach ($asset->getFiles() as $slot) {
            if ($slot->getAssetFile() === $asset->getMainFile()) {
                return $slot;
            }
        }

        return null;
    }

    private function getDefaultSlot(Asset $asset): ?AssetHasFile
    {
        foreach ($asset->getFiles() as $slot) {
            if ($slot->isDefault()) {
                return $slot;
            }
        }

        return null;
    }
}
