<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Asset;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Model\Dto\AbstractEntityDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\Embeds\AssetAttributesAdmDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\Embeds\AssetTextsAdmListDto;
use AnzuSystems\CoreDamBundle\Model\Enum\ImageCropTag;
use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\AssetFileHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

class AssetAdmListDto extends AbstractEntityDto
{
    protected string $resourceName = Asset::class;
    protected Asset $asset;

    #[Serialize]
    protected AssetTextsAdmListDto $texts;

    #[Serialize]
    protected AssetAttributesAdmDto $attributes;

    public static function getInstance(Asset $asset): static
    {
        return parent::getBaseInstance($asset)
            ->setTexts(AssetTextsAdmListDto::getInstance($asset->getTexts()))
            ->setAttributes(AssetAttributesAdmDto::getInstance($asset->getAttributes()))
            ->setAsset($asset);
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

    #[Serialize(handler: AssetFileHandler::class, type: ImageCropTag::LIST)]
    public function getMainFile(): ?AssetFile
    {
        return $this->asset->getMainFile();
    }

    public function getAttributes(): AssetAttributesAdmDto
    {
        return $this->attributes;
    }

    public function setAttributes(AssetAttributesAdmDto $attributes): self
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function getTexts(): AssetTextsAdmListDto
    {
        return $this->texts;
    }

    public function setTexts(AssetTextsAdmListDto $texts): self
    {
        $this->texts = $texts;

        return $this;
    }
}
