<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Image;

use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFileMetadata\AssetFileMetadataAdmDetailDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFileRoute\AssetFileRouteAdmDetailDecorator;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetMetadata\AssetMetadataAdmDetailDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\Embeds\ImageAttributesAdmDto;
use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\LinksHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class ImageFileAdmDetailDto extends ImageFileAdmListDto
{
    #[Serialize]
    protected ImageAttributesAdmDto $imageAttributes;

    #[Serialize]
    protected AssetFileMetadataAdmDetailDto $metadata;

    #[Serialize]
    protected AssetMetadataAdmDetailDto $assetMetadata;

    public function __construct()
    {
        parent::__construct();
        $this->setImageAttributes(new ImageAttributesAdmDto());
        $this->setMetadata(new AssetFileMetadataAdmDetailDto());
        $this->setAssetMetadata(new AssetMetadataAdmDetailDto());
    }

    public static function getInstance(ImageFile $image): static
    {
        /** @psalm-var ImageFileAdmDetailDto $parent */
        $parent = parent::getInstance($image);

        return $parent
            ->setImageAttributes(ImageAttributesAdmDto::getInstance($image->getImageAttributes()))
            ->setMetadata(AssetFileMetadataAdmDetailDto::getInstance($image->getMetadata()))
            ->setAssetMetadata(AssetMetadataAdmDetailDto::getInstance($image->getAsset()->getMetadata()))
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

    #[Serialize(handler: LinksHandler::class)]
    public function getLinks(): ImageFile
    {
        return $this->image;
    }

    #[Serialize]
    public function getOriginAssetFile(): string
    {
        return $this->image->getAssetAttributes()->getOriginAssetId();
    }

    #[Serialize]
    public function getMainRoute(): ?AssetFileRouteAdmDetailDecorator
    {
        return $this->image->getMainRoute()
            ? AssetFileRouteAdmDetailDecorator::getInstance($this->image->getMainRoute())
            : null
        ;
    }
}
