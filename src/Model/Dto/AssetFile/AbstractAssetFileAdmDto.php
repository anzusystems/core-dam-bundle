<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetFile;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Model\Dto\AbstractEntityDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\Embeds\AssetFileAttributesAdmDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\Embeds\AssetFileFlagsAdmDto;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;

abstract class AbstractAssetFileAdmDto extends AbstractEntityDto
{
    #[Serialize(handler: EntityIdHandler::class)]
    protected Asset $asset;

    #[Serialize]
    protected AssetFileAttributesAdmDto $fileAttributes;

    #[Serialize]
    protected AssetFileFlagsAdmDto $flags;

    public function __construct()
    {
        $this->setFileAttributes(new AssetFileAttributesAdmDto());
        $this->setFlags(new AssetFileFlagsAdmDto());
    }

    public static function getAssetFileBaseInstance(AssetFile $assetFile): static
    {
        $parent = parent::getBaseInstance($assetFile);

        return $parent
            ->setAsset($assetFile->getAsset())
            ->setFileAttributes(AssetFileAttributesAdmDto::getInstance($assetFile->getAssetAttributes()))
            ->setFlags(AssetFileFlagsAdmDto::getInstance($assetFile->getFlags()))
        ;
    }

    #[Serialize(handler: EntityIdHandler::class)]
    public function getAsset(): Asset
    {
        return $this->asset;
    }

    public function setAsset(Asset $asset): self
    {
        $this->asset = $asset;

        return $this;
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

    public function getFlags(): AssetFileFlagsAdmDto
    {
        return $this->flags;
    }

    public function setFlags(AssetFileFlagsAdmDto $flags): self
    {
        $this->flags = $flags;

        return $this;
    }
}
