<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\RssFeed;

final class ItemItunes
{
    private string $episode = '';
    private string $season = '';
    private string $duration = '';
    private string $explicit = '';
    private string $episodeType = '';
    private string $image = '';

    public function getEpisode(): string
    {
        return $this->episode;
    }

    public function setEpisode(string $episode): self
    {
        $this->episode = $episode;

        return $this;
    }

    public function getSeason(): string
    {
        return $this->season;
    }

    public function setSeason(string $season): self
    {
        $this->season = $season;

        return $this;
    }

    public function getDuration(): string
    {
        return $this->duration;
    }

    public function setDuration(string $duration): self
    {
        $this->duration = $duration;

        return $this;
    }

    public function getExplicit(): string
    {
        return $this->explicit;
    }

    public function setExplicit(string $explicit): self
    {
        $this->explicit = $explicit;

        return $this;
    }

    public function getEpisodeType(): string
    {
        return $this->episodeType;
    }

    public function setEpisodeType(string $episodeType): self
    {
        $this->episodeType = $episodeType;

        return $this;
    }

    public function getImage(): string
    {
        return $this->image;
    }

    public function setImage(string $image): self
    {
        $this->image = $image;

        return $this;
    }
}
