<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Youtube;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Google_Service_YouTube_PlaylistListResponse;

class PlaylistCollectionDto
{
    #[Serialize]
    private string $etag;

    #[Serialize]
    private ?string $nextPageToken;

    #[Serialize(type: PlaylistDto::class)]
    private array $playlists;

    public function __construct()
    {
        $this->setEtag('');
        $this->setPlaylists([]);
        $this->setNextPageToken(null);
    }

    public static function createFromGoogle(Google_Service_YouTube_PlaylistListResponse $response): self
    {
        $self = (new self())
            ->setEtag($response->etag)
            ->setNextPageToken($response->nextPageToken);

        foreach ($response->getItems() as $item) {
            $self->addPlaylist(PlaylistDto::createFromGoogle($item));
        }

        return $self;
    }

    public function getEtag(): string
    {
        return $this->etag;
    }

    public function setEtag(string $etag): self
    {
        $this->etag = $etag;

        return $this;
    }

    public function getPlaylists(): array
    {
        return $this->playlists;
    }

    public function setPlaylists(array $playlists): self
    {
        $this->playlists = $playlists;

        return $this;
    }

    public function addPlaylist(PlaylistDto $playlistDto): self
    {
        $this->playlists[] = $playlistDto;

        return $this;
    }

    public function getNextPageToken(): ?string
    {
        return $this->nextPageToken;
    }

    public function setNextPageToken(?string $nextPageToken): self
    {
        $this->nextPageToken = $nextPageToken;

        return $this;
    }
}
