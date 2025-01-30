<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\JwVideo;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

final class JwVideoMediaCdnDto
{
    #[Serialize]
    private string $title = '';

    #[Serialize]
    private string $description = '';

    #[Serialize(type: JwVideoMediaPlaylistCdnDto::class)]
    private Collection $playlist;

    public function __construct()
    {
        $this->setPlaylist(new ArrayCollection());
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

    /**
     * @return Collection<int, JwVideoMediaPlaylistCdnDto>
     */
    public function getPlaylist(): Collection
    {
        return $this->playlist;
    }

    public function setPlaylist(Collection $playlist): self
    {
        $this->playlist = $playlist;

        return $this;
    }
}
