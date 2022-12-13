<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Youtube;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Google_Service_YouTube_Playlist;

class PlaylistDto
{
    #[Serialize]
    private string $id = '';

    #[Serialize]
    private string $channelTitle = '';

    #[Serialize]
    private string $description = '';

    #[Serialize]
    private string $title = '';

    public static function createFromGoogle(Google_Service_YouTube_Playlist $playlistItem): self
    {
        return (new self())
            ->setId($playlistItem->getId())
            ->setTitle($playlistItem->getSnippet()->getTitle())
            ->setDescription($playlistItem->getSnippet()->getDescription())
            ->setChannelTitle($playlistItem->getSnippet()->getChannelTitle())
        ;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getChannelTitle(): string
    {
        return $this->channelTitle;
    }

    public function setChannelTitle(string $channelTitle): self
    {
        $this->channelTitle = $channelTitle;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }
}
