<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Asset;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileFactory;
use AnzuSystems\CoreDamBundle\Domain\AssetMetadata\AssetMetadataManager;
use AnzuSystems\CoreDamBundle\Domain\AssetSlot\AssetSlotManager;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFileCopyBuilder;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\AssetSlot;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmUpdateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\FormProvidableMetadataBulkUpdateDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\NonUniqueResultException;

class AssetCopyBuilder extends AbstractManager
{
    public function __construct(
        private readonly ImageFileCopyBuilder $imageFileCopyBuilder,
        private readonly AssetMetadataManager $assetMetadataManager,
        private readonly ImageManager $imageManager,
        private readonly AssetFileFactory $assetFileFactory,
        private readonly AssetSlotManager $assetSlotManager,
    ) {
    }

    public function copyDraft(Asset $asset, AssetLicence $assetLicence, bool $flush = true): Asset
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
        $assetCopy->setSlots(
            $asset->getSlots()->map(
                fn (AssetSlot $slot): AssetSlot => $this->copySlotToAsset($slot, $assetCopy)
            )
        );
    }

    private function copySlotToAsset(AssetSlot $assetSlot, Asset $assetCopy): AssetSlot
    {
        $slotCopy = $assetSlot->__copy();
        $this->assetSlotManager->create($slotCopy, false);
        $assetFile = $this->assetFileFactory->createForAsset($assetCopy);
        $assetSlot->setAsset($assetCopy);
        $assetFile->addSlot($slotCopy);


        return $slotCopy;
    }
}
