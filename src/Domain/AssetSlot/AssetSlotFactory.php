<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetSlot;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetSlot;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\DocumentFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Exception\DomainException;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAssetTypeConfiguration;

readonly class AssetSlotFactory
{
    public function __construct(
        private AssetSlotManager $manager,
        private ExtSystemConfigurationProvider $configurationProvider,
    ) {
    }

    public function createRelation(Asset $asset, AssetFile $assetFile, ?string $slotName = null, bool $flush = true): AssetSlot
    {
        $isMainSlot = $asset->getSlots()->isEmpty();

        $assetSlot = $this->initRelationEntity($asset, $slotName);
        $asset->addSlot($assetSlot);
        $assetFile->addSlot($assetSlot);
        $assetFile->setAsset($asset);

        if ($isMainSlot) {
            $assetSlot->getFlags()->setMain(true);
            $asset->setMainFile($assetFile);
        }

        match ($assetFile::class) {
            ImageFile::class => $assetSlot->setImage($assetFile),
            AudioFile::class => $assetSlot->setAudio($assetFile),
            DocumentFile::class => $assetSlot->setDocument($assetFile),
            VideoFile::class => $assetSlot->setVideo($assetFile),
        };

        return $this->manager->create($assetSlot, $flush);
    }

    public function getSlotName(
        ExtSystemAssetTypeConfiguration $configuration,
        ?string $slotName = null
    ): string {
        if (empty($slotName)) {
            return $configuration->getSlots()->getDefault();
        }

        if (in_array($slotName, $configuration->getSlots()->getSlots(), true)) {
            return $slotName;
        }

        throw new DomainException('invalid_slot_name');
    }

    private function initRelationEntity(Asset $asset, ?string $slotName = null): AssetSlot
    {
        $configuration = $this->configurationProvider->getExtSystemConfigurationByAsset($asset);
        $assetSlot = new AssetSlot();

        $actualSlotName = $this->getSlotName($configuration, $slotName);

        $assetSlot->setName($actualSlotName);
        $assetSlot->getFlags()->setDefault($configuration->getSlots()->getDefault() === $actualSlotName);

        return $assetSlot;
    }
}
