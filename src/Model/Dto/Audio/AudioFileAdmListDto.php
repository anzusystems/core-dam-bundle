<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Audio;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\ImagePreview;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AbstractAssetFileAdmDto;
use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\LinksHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;

class AudioFileAdmListDto extends AbstractAssetFileAdmDto
{
    protected string $resourceName = AudioFile::class;

    #[Serialize(serializedName: 'id', handler: EntityIdHandler::class)]
    protected AudioFile $audio;

    public static function getInstance(AudioFile $audioFile): static
    {
        /** @psalm-var AudioFileAdmListDto $parent */
        $parent = parent::getAssetFileBaseInstance($audioFile);

        return $parent
            ->setAudio($audioFile);
    }

    public function getAudio(): AudioFile
    {
        return $this->audio;
    }

    public function setAudio(AudioFile $audio): self
    {
        $this->audio = $audio;

        return $this;
    }

    #[Serialize(handler: EntityIdHandler::class)]
    public function getAsset(): Asset
    {
        return $this->audio->getAsset();
    }

    #[Serialize(handler: LinksHandler::class)]
    public function getLinks(): AudioFile
    {
        return $this->audio;
    }

    #[Serialize]
    public function getImagePreview(): ?ImagePreview
    {
        $firstEpisode = $this->audio->getAsset()->getEpisodes()->first();

        if ($firstEpisode instanceof PodcastEpisode) {
            return $firstEpisode->getImagePreview() ?? $firstEpisode->getPodcast()->getImagePreview();
        }

        return null;
    }
}
