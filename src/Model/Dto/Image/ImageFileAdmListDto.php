<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Image;

use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AbstractAssetFileAdmDto;
use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\LinksHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;

class ImageFileAdmListDto extends AbstractAssetFileAdmDto
{
    protected string $resourceName = ImageFile::class;

    #[Serialize(serializedName: 'id', handler: EntityIdHandler::class)]
    protected ImageFile $image;

    public static function getInstance(ImageFile $image): static
    {
        /** @psalm-var ImageFileAdmListDto $parent */
        $parent = parent::getAssetFileBaseInstance($image);

        return $parent
            ->setImage($image);
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

    #[Serialize(handler: LinksHandler::class)]
    public function getLinks(): ImageFile
    {
        return $this->image;
    }
}
