<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Image;

use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFileMetadata\AssetFileMetadataAdmDetailDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetMetadata\AssetMetadataAdmDetailDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\Embeds\ImageAttributesAdmDto;
use AnzuSystems\CoreDamBundle\Model\Enum\ImageCropTag;
use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\ImageLinksHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class ImageFileAdmDetailDto extends ImageFileAdmListDto
{
    #[Serialize]
    protected ImageAttributesAdmDto $imageAttributes;

    #[Serialize]
    protected AssetFileMetadataAdmDetailDto $metadata;

    #[Serialize]
    protected AssetMetadataAdmDetailDto $assetMetadata;

    public static function getInstance(ImageFile $image): static
    {
        return parent::getInstance($image)
            ->setImageAttributes(ImageAttributesAdmDto::getInstance($image->getImageAttributes()))
            ->setMetadata(AssetFileMetadataAdmDetailDto::getInstance($image->getMetadata()))
            ->setAssetMetadata(AssetMetadataAdmDetailDto::getInstance($image->getAsset()->getAsset()->getMetadata()))
        ;
    }

    public function getMetadata(): AssetFileMetadataAdmDetailDto
    {
        return $this->metadata;
    }

    public function setMetadata(AssetFileMetadataAdmDetailDto $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getImageAttributes(): ImageAttributesAdmDto
    {
        return $this->imageAttributes;
    }

    public function setImageAttributes(ImageAttributesAdmDto $imageAttributes): self
    {
        $this->imageAttributes = $imageAttributes;

        return $this;
    }

    public function getAssetMetadata(): AssetMetadataAdmDetailDto
    {
        return $this->assetMetadata;
    }

    public function setAssetMetadata(AssetMetadataAdmDetailDto $assetMetadata): self
    {
        $this->assetMetadata = $assetMetadata;

        return $this;
    }

    #[Serialize(handler: ImageLinksHandler::class, type: ImageCropTag::DETAIL)]
    public function getLinks(): ImageFile
    {
        return $this->image;
    }

    #[Serialize]
    public function getOriginAssetFile(): string
    {
        return $this->image->getAssetAttributes()->getOriginAssetId();
    }
}
