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

        $this->syncMainFile($asset);
        $this->refreshMainFile($asset);
        $this->refreshStatus($asset);

        return $asset;
    }

    /**
     * Updates slot flags based on asset main file
     */
    private function syncMainFile(Asset $asset)
    {
        $asset->getSlots()->map(
            fn (AssetSlot $slot) => $slot->getFlags()->setMain(
                $slot->getAssetFile() === $asset->getMainFile()
            )
        );
    }

    private function refreshStatus(Asset $asset): void
    {
        if ($asset->getAttributes()->getStatus()->is(AssetStatus::Deleting)) {
            return;
        }

        foreach ($asset->getSlots() as $slot) {
            if ($slot->getAssetFile()->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Processed)) {
                $asset->getAttributes()->setStatus(AssetStatus::WithFile);

                return;
            }
        }

        $asset->getAttributes()->setStatus(AssetStatus::Draft);
    }

    /**
     * If there is no main file, try to set new main file
     */
    private function refreshMainFile(Asset $asset): void
    {
        if ($asset->getMainFile()) {
            return;
        }

        $newMainFileSlot = $this->getDefaultSlot($asset) ?? $asset->getSlots()->first();
        if ($newMainFileSlot instanceof AssetSlot) {
            $newMainFileSlot->getFlags()->setMain(true);
            $asset->setMainFile($newMainFileSlot->getAssetFile());

            return;
        }

        $asset->setMainFile(null);
    }

    private function getDefaultSlot(Asset $asset): ?AssetSlot
    {
        foreach ($asset->getSlots() as $slot) {
            if ($slot->getFlags()->isDefault()) {
                return $slot;
            }
        }

        return null;
    }
}
