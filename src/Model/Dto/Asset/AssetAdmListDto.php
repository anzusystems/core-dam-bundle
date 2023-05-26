<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Asset;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\CoreDamBundle\Model\Dto\AbstractEntityDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\Embeds\AssetAttributesAdmDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\Embeds\AssetFilePropertiesAdmDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\Embeds\AssetTextsAdmListDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Model\Enum\ImageCropTag;
use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\AssetFileHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

class AssetAdmListDto extends AbstractEntityDto
{
    protected string $resourceName = Asset::class;
    protected Asset $asset;
    protected AssetTextsAdmListDto $texts;
    protected AssetAttributesAdmDto $attributes;
    protected AssetFilePropertiesAdmDto $assetFileProperties;

    public static function getInstance(Asset $asset): static
    {
        /** @psalm-var AssetAdmListDto $parent */
        $parent = parent::getBaseInstance($asset);

        return $parent
            ->setTexts(AssetTextsAdmListDto::getInstance($asset->getTexts()))
            ->setAttributes(AssetAttributesAdmDto::getInstance($asset->getAttributes()))
            ->setAssetFileProperties(AssetFilePropertiesAdmDto::getInstance($asset->getAssetFileProperties()))
            ->setAsset($asset);
    }

    public function getAsset(): Asset
    {
        return $this->asset;
    }

    public function setAsset(Asset $asset): static
    {
        $this->asset = $asset;

        return $this;
    }

    #[Serialize(handler: AssetFileHandler::class, type: ImageCropTag::LIST)]
    public function getMainFile(): ?AssetFile
    {
        return $this->asset->getMainFile();
    }

    #[Serialize]
    public function getAttributes(): AssetAttributesAdmDto
    {
        return $this->attributes;
    }

    public function setAttributes(AssetAttributesAdmDto $attributes): static
    {
        $this->attributes = $attributes;

        return $this;
    }

    #[Serialize]
    public function getTexts(): AssetTextsAdmListDto
    {
        return $this->texts;
    }

    public function setTexts(AssetTextsAdmListDto $texts): static
    {
        $this->texts = $texts;

        return $this;
    }

    #[Serialize]
    public function getAssetFileProperties(): AssetFilePropertiesAdmDto
    {
        return $this->assetFileProperties;
    }

    public function setAssetFileProperties(AssetFilePropertiesAdmDto $assetFileProperties): static
    {
        $this->assetFileProperties = $assetFileProperties;

        return $this;
    }

    #[Serialize]
    public function getPodcasts(): array
    {
        if ($this->asset->getAttributes()->getAssetType()->is(AssetType::Audio)) {
            return $this->asset->getEpisodes()->map(
                fn (PodcastEpisode $episode): string => (string) $episode->getPodcast()->getId()
            )->getValues();
        }

        return [];
    }
}
