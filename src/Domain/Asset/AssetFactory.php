<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Asset;

use AnzuSystems\CoreDamBundle\Domain\AssetMetadata\AssetMetadataManager;
use AnzuSystems\CoreDamBundle\Domain\AssetSlot\AssetSlotFactory;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\AssetMetadata;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\DocumentFile;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetAttributes;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmCreateDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;

final readonly class AssetFactory
{
    public function __construct(
        private AssetManager $assetManager,
        private AssetSlotFactory $assetSlotFactory,
        private AssetMetadataManager $assetMetadataManager,
    ) {
    }

    public function createFromAdmDto(AssetAdmCreateDto $createDto, AssetLicence $assetLicence): Asset
    {
        return $this->initAsset($createDto->getType(), $assetLicence);
    }

    public function createForAssetFile(
        AssetFile $assetFile,
        AssetLicence $assetLicence,
        ?string $slotName = null,
        ?string $id = null
    ): Asset {
        $asset = match ($assetFile::class) {
            ImageFile::class => $this->assetManager->create($this->createForImageFile($assetFile, $assetLicence, $slotName, $id), false),
            AudioFile::class => $this->assetManager->create($this->createForAudioFile($assetFile, $assetLicence, $slotName, $id), false),
            VideoFile::class => $this->assetManager->create($this->createForVideoFile($assetFile, $assetLicence, $slotName, $id), false),
            DocumentFile::class => $this->assetManager->create($this->createForDocumentFile($assetFile, $assetLicence, $slotName, $id), false),
        };
        $asset
            ->setLicence($assetLicence)
            ->setExtSystem($assetLicence->getExtSystem())
        ;

        return $asset;
    }

    private function createForImageFile(
        ImageFile $imageFile,
        AssetLicence $assetLicence,
        ?string $slotName = null,
        ?string $id = null
    ): Asset {
        $asset = $this->initAsset(AssetType::Image, $assetLicence, $id);
        $this->assetSlotFactory->createRelation(asset: $asset, assetFile: $imageFile, slotName: $slotName, flush: false);

        return $asset;
    }

    private function createForAudioFile(
        AudioFile $audioFile,
        AssetLicence $assetLicence,
        ?string $slotName = null,
        ?string $id = null
    ): Asset {
        $asset = $this->initAsset(AssetType::Audio, $assetLicence, $id);
        $this->assetSlotFactory->createRelation(asset: $asset, assetFile: $audioFile, slotName: $slotName, flush: false);

        return $asset;
    }

    private function createForVideoFile(
        VideoFile $videoFile,
        AssetLicence $assetLicence,
        ?string $slotName = null,
        ?string $id = null
    ): Asset {
        $asset = $this->initAsset(AssetType::Video, $assetLicence, $id);
        $this->assetSlotFactory->createRelation(asset: $asset, assetFile: $videoFile, slotName: $slotName, flush: false);

        return $asset;
    }

    private function createForDocumentFile(
        DocumentFile $documentFile,
        AssetLicence $assetLicence,
        ?string $slotName = null,
        ?string $id = null
    ): Asset {
        $asset = $this->initAsset(AssetType::Document, $assetLicence, $id);
        $this->assetSlotFactory->createRelation(asset: $asset, assetFile: $documentFile, slotName: $slotName, flush: false);

        return $asset;
    }

    private function initAsset(AssetType $assetType, AssetLicence $assetLicence, ?string $id = null): Asset
    {
        $asset = (new Asset())
            ->setLicence($assetLicence)
            ->setExtSystem($assetLicence->getExtSystem())
            ->setMetadata(
                $this->assetMetadataManager->create(new AssetMetadata(), false)
            )
            ->setAttributes(
                (new AssetAttributes())
                    ->setAssetType($assetType)
            );

        if ($id) {
            $asset->setId($id);
        }

        return $asset;
    }
}
