<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Image;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Model\Dto\AbstractEntityDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\Embeds\AssetFileAttributesAdmDto;
use AnzuSystems\CoreDamBundle\Model\Enum\ImageCropTag;
use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\ImageLinksHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;

class ImageFileAdmListDto extends AbstractEntityDto
{
    protected string $resourceName = ImageFile::class;
    protected ImageFile $image;

    #[Serialize(handler: EntityIdHandler::class)]
    protected Asset $asset;

    #[Serialize]
    protected AssetFileAttributesAdmDto $fileAttributes;

    public static function getInstance(ImageFile $image): static
    {
        return parent::getBaseInstance($image)
            ->setAsset($image->getAsset()->getAsset())
            ->setFileAttributes(AssetFileAttributesAdmDto::getInstance($image->getAssetAttributes()))
            ->setImage($image);
    }

    public function getFileAttributes(): AssetFileAttributesAdmDto
    {
        return $this->fileAttributes;
    }

    public function setFileAttributes(AssetFileAttributesAdmDto $fileAttributes): self
    {
        $this->fileAttributes = $fileAttributes;

        return $this;
    }

    public function getImage(): ImageFile
    {
        return $this->image;
    }

    public function setImage(ImageFile $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function getAsset(): Asset
    {
        return $this->asset;
    }

    public function setAsset(Asset $asset): self
    {
        $this->asset = $asset;

        return $this;
    }

    #[Serialize(handler: ImageLinksHandler::class, type: ImageCropTag::LIST)]
    public function getLinks(): ImageFile
    {
        return $this->image;
    }
}
