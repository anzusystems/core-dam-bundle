<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Asset;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetSlot;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetStatus;
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
        $this->refreshStatus($asset);

        return $asset;
    }

    private function refreshStatus(Asset $asset): void
    {
        foreach ($asset->getSlots() as $slot) {
            if ($slot->getAssetFile()->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Processed)) {
                $asset->getAttributes()->setStatus(AssetStatus::WithFile);

                return;
            }
        }

        $asset->getAttributes()->setStatus(AssetStatus::Draft);
    }

    private function refreshMainFile(Asset $asset): void
    {
        $manFileSlot = $this->getMainFileSlot($asset);
        if ($manFileSlot instanceof AssetSlot) {
            return;
        }

        $newMainFileSlot = $this->getDefaultSlot($asset) ?? $asset->getSlots()->first();
        if ($newMainFileSlot instanceof AssetSlot) {
            $asset->setMainFile($newMainFileSlot->getAssetFile());

            return;
        }

        $asset->setMainFile(null);
    }

    private function getMainFileSlot(Asset $asset): ?AssetSlot
    {
        if (null === $asset->getMainFile()) {
            return null;
        }

        foreach ($asset->getSlots() as $slot) {
            if ($slot->getAssetFile() === $asset->getMainFile()) {
                return $slot;
            }
        }

        return null;
    }

    private function getDefaultSlot(Asset $asset): ?AssetSlot
    {
        foreach ($asset->getSlots() as $slot) {
            if ($slot->isDefault()) {
                return $slot;
            }
        }

        return null;
    }
}
