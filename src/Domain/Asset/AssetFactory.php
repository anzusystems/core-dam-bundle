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

final class AssetFactory
{
    public function __construct(
        private readonly AssetManager $assetManager,
        private readonly AssetSlotFactory $assetSlotFactory,
        private readonly AssetMetadataManager $assetMetadataManager,
    ) {
    }

    public function createFromAdmDto(AssetAdmCreateDto $createDto, AssetLicence $assetLicence): Asset
    {
        return $this->initAsset($createDto->getType(), $assetLicence);
    }

    // @todo remove licence
    public function createForAssetFile(AssetFile $assetFile, AssetLicence $assetLicence, ?string $slotName = null): Asset
    {
        $asset = match ($assetFile::class) {
            ImageFile::class => $this->assetManager->create($this->createForImageFile($assetFile, $assetLicence, $slotName), false),
            AudioFile::class => $this->assetManager->create($this->createForAudioFile($assetFile, $assetLicence, $slotName), false),
            VideoFile::class => $this->assetManager->create($this->createForVideoFile($assetFile, $assetLicence, $slotName), false),
            DocumentFile::class => $this->assetManager->create($this->createForDocumentFile($assetFile, $assetLicence, $slotName), false),
        };
        $asset->setLicence($assetLicence);

        return $asset;
    }

    private function createForImageFile(ImageFile $imageFile, AssetLicence $assetLicence, ?string $slotName = null): Asset
    {
        $asset = $this->initAsset(AssetType::Image, $assetLicence);
        $this->assetSlotFactory->createRelation(asset: $asset, assetFile: $imageFile, slotName: $slotName, flush: false);

        return $asset;
    }

    private function createForAudioFile(AudioFile $audioFile, AssetLicence $assetLicence, ?string $slotName = null): Asset
    {
        $asset = $this->initAsset(AssetType::Audio, $assetLicence);
        $this->assetSlotFactory->createRelation(asset: $asset, assetFile: $audioFile, slotName: $slotName, flush: false);

        return $asset;
    }

    private function createForVideoFile(VideoFile $videoFile, AssetLicence $assetLicence, ?string $slotName = null): Asset
    {
        $asset = $this->initAsset(AssetType::Video, $assetLicence);
        $this->assetSlotFactory->createRelation(asset: $asset, assetFile: $videoFile, slotName: $slotName, flush: false);

        return $asset;
    }

    private function createForDocumentFile(DocumentFile $documentFile, AssetLicence $assetLicence, ?string $slotName = null): Asset
    {
        $asset = $this->initAsset(AssetType::Document, $assetLicence);
        $this->assetSlotFactory->createRelation(asset: $asset, assetFile: $documentFile, slotName: $slotName, flush: false);

        return $asset;
    }

    private function initAsset(AssetType $assetType, AssetLicence $assetLicence): Asset
    {
        return (new Asset())
            ->setLicence($assetLicence)
            ->setMetadata(
                $this->assetMetadataManager->create(new AssetMetadata(), false)
            )
            ->setAttributes(
                (new AssetAttributes())
                    ->setAssetType($assetType)
            );
    }
}
