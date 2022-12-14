<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Asset;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\Keyword;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\Embeds\AssetFlagsAdmDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\Embeds\AssetTextsAdmListDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetMetadata\AssetMetadataAdmDetailDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Model\Enum\ImageCropTag;
use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\MainFileHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\Common\Collections\Collection;

final class AssetAdmDetailDto extends AssetAdmListDto
{
    #[Serialize]
    protected AssetTextsAdmListDto $texts;

    #[Serialize(handler: EntityIdHandler::class, type: Keyword::class)]
    protected Collection $keywords;

    #[Serialize(handler: EntityIdHandler::class, type: Author::class)]
    protected Collection $authors;

    #[Serialize]
    private AssetFlagsAdmDto $flags;

    #[Serialize(handler: EntityIdHandler::class)]
    private AssetLicence $licence;

    #[Serialize]
    private AssetMetadataAdmDetailDto $metadata;

    public static function getInstance(Asset $asset): static
    {
        return parent::getInstance($asset)
            ->setTexts(AssetTextsAdmListDto::getInstance($asset->getTexts()))
            ->setFlags(AssetFlagsAdmDto::getInstance($asset->getAssetFlags()))
            ->setLicence($asset->getLicence())
            ->setMetadata(AssetMetadataAdmDetailDto::getInstance($asset->getMetadata()))
            ->setKeywords($asset->getKeywords())
            ->setAuthors($asset->getAuthors())
        ;
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

    public function getFlags(): AssetFlagsAdmDto
    {
        return $this->flags;
    }

    public function setFlags(AssetFlagsAdmDto $flags): self
    {
        $this->flags = $flags;

        return $this;
    }

    public function getLicence(): AssetLicence
    {
        return $this->licence;
    }

    public function setLicence(AssetLicence $licence): self
    {
        $this->licence = $licence;

        return $this;
    }

    public function getMetadata(): AssetMetadataAdmDetailDto
    {
        return $this->metadata;
    }

    public function setMetadata(AssetMetadataAdmDetailDto $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getKeywords(): Collection
    {
        return $this->keywords;
    }

    public function setKeywords(Collection $keywords): self
    {
        $this->keywords = $keywords;

        return $this;
    }

    public function getAuthors(): Collection
    {
        return $this->authors;
    }

    public function setAuthors(Collection $authors): self
    {
        $this->authors = $authors;

        return $this;
    }

    #[Serialize(handler: MainFileHandler::class, type: ImageCropTag::DETAIL)]
    public function getMainFile(): Asset
    {
        return $this->asset;
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
