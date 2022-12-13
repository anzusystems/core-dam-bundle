<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\RssFeed;

final class Channel
{
    private string $title = '';
    private string $description = '';
    private string $language = '';
    private ChannelItunes $itunes;

    public function __construct()
    {
        $this->setItunes(new ChannelItunes());
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

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): self
    {
        $this->language = $language;

        return $this;
    }

    public function getItunes(): ChannelItunes
    {
        return $this->itunes;
    }

    public function setItunes(ChannelItunes $itunes): self
    {
        $this->itunes = $itunes;

        return $this;
    }
}
