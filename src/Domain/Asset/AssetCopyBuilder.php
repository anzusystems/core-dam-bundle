<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Asset;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileFactory;
use AnzuSystems\CoreDamBundle\Domain\AssetMetadata\AssetMetadataManager;
use AnzuSystems\CoreDamBundle\Domain\AssetSlot\AssetSlotManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\AssetSlot;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetStatus;

class AssetCopyBuilder extends AbstractManager
{
    public function __construct(
        private readonly AssetMetadataManager $assetMetadataManager,
        private readonly AssetFileFactory $assetFileFactory,
        private readonly AssetSlotManager $assetSlotManager,
    ) {
    }

    public function buildAssetFilesCopy(Asset $asset, Asset $copyAsset)
    {
        foreach ($copyAsset->getSlots() as $targetSlot) {
            $assetSlot = $asset->getSlots()->findFirst(
                fn (int $index, AssetSlot $assetSlot) => $assetSlot->getName() === $targetSlot->getName()
            );

            if ($assetSlot instanceof AssetSlot) {
                $this->copyAssetSlot($assetSlot, $targetSlot);

                continue;
            }

            // todo failed ... not found!
        }
    }

    public function buildDraftAssetCopy(Asset $asset, AssetLicence $assetLicence, bool $flush = true): Asset
    {
        // todo NOTIF!
        // todo setup main file
        $assetCopy = $asset->__copy();
        $assetCopy->getAttributes()->setStatus(AssetStatus::Draft);
        $this->trackCreation($assetCopy);
        $assetCopy->setLicence($assetLicence);
        $assetCopy->setExtSystem($assetLicence->getExtSystem());
        $this->entityManager->persist($assetCopy);
        $this->assetMetadataManager->create($assetCopy->getMetadata(), false);
        $this->copySlots($asset, $assetCopy);

        foreach ($assetCopy->getSlots() as $assetSlot) {
            if ($assetSlot->getFlags()->isMain()) {
                $assetCopy->setMainFile($assetSlot->getAssetFile());

                break;
            }
        }

        $this->flush($flush);

        return $assetCopy;
    }

    private function copySlots(Asset $asset, Asset $assetCopy): void
    {
        foreach ($asset->getSlots() as $assetSlot) {
            $slotCopy = $this->copySlotToAsset($assetSlot, $assetCopy);
            $assetCopy->addSlot($slotCopy);
            $slotCopy->setAsset($assetCopy);
        }
    }

    private function copySlotToAsset(AssetSlot $assetSlot, Asset $assetCopy): AssetSlot
    {
        $slotCopy = $assetSlot->__copy();
        $blankAssetFile = $this->assetFileFactory->createForAsset($assetCopy);
        $blankAssetFile->setAsset($assetCopy);
        $blankAssetFile->addSlot($slotCopy);
        $this->assetSlotManager->create($slotCopy, false);

        return $slotCopy;
    }
}
