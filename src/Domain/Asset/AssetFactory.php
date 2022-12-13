<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Asset;

use AnzuSystems\CoreDamBundle\Domain\AssetHasFile\AssetHasFileFactory;
use AnzuSystems\CoreDamBundle\Domain\AssetMetadata\AssetMetadataManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\AssetMetadata;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\DocumentFile;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetAttributes;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetTexts;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmCreateDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;

final class AssetFactory
{
    public function __construct(
        private readonly AssetManager $assetManager,
        private readonly AssetHasFileFactory $assetHasFileFactory,
        private readonly AssetMetadataManager $assetMetadataManager,
    ) {
    }

    public function createFromAdmDto(AssetAdmCreateDto $createDto, AssetLicence $assetLicence): Asset
    {
        return $this->initAsset($createDto->getType(), $assetLicence)
            ->setTexts(
                (new AssetTexts())
            );
    }

    public function createForAssetFile(AssetFile $assetFile, AssetLicence $assetLicence): Asset
    {
        $asset = match ($assetFile::class) {
            ImageFile::class => $this->assetManager->create($this->createForImageFile($assetFile, $assetLicence), false),
            AudioFile::class => $this->assetManager->create($this->createForAudioFile($assetFile, $assetLicence), false),
            VideoFile::class => $this->assetManager->create($this->createForVideoFile($assetFile, $assetLicence), false),
            DocumentFile::class => $this->assetManager->create($this->createForDocumentFile($assetFile, $assetLicence), false),
        };
        $asset->setLicence($assetLicence);

        return $asset;
    }

    private function createForImageFile(ImageFile $imageFile, AssetLicence $assetLicence): Asset
    {
        $asset = $this->initAsset(AssetType::Image, $assetLicence);
        $this->assetHasFileFactory->createRelation(asset: $asset, assetFile: $imageFile, flush: false);

        return $asset;
    }

    private function createForAudioFile(AudioFile $audioFile, AssetLicence $assetLicence): Asset
    {
        $asset = $this->initAsset(AssetType::Audio, $assetLicence);
        $this->assetHasFileFactory->createRelation(asset: $asset, assetFile: $audioFile, flush: false);

        return $asset;
    }

    private function createForVideoFile(VideoFile $videoFile, AssetLicence $assetLicence): Asset
    {
        $asset = $this->initAsset(AssetType::Video, $assetLicence);
        $this->assetHasFileFactory->createRelation(asset: $asset, assetFile: $videoFile, flush: false);

        return $asset;
    }

    private function createForDocumentFile(DocumentFile $documentFile, AssetLicence $assetLicence): Asset
    {
        $asset = $this->initAsset(AssetType::Document, $assetLicence);
        $this->assetHasFileFactory->createRelation(asset: $asset, assetFile: $documentFile, flush: false);

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
