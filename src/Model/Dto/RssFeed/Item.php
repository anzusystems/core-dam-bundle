<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\RssFeed;

use DateTimeImmutable;

final class Item
{
    private string $title = '';
    private string $description = '';
    private string $link = '';
    private string $guid = '';
    private string $creator = '';
    private ?DateTimeImmutable $pubDate = null;
    private ItemEnclosure $enclosure;
    private ItemItunes $itunes;

    public function __construct()
    {
        $this->setItunes(new ItemItunes());
        $this->setEnclosure(new ItemEnclosure());
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

    public function getLink(): string
    {
        return $this->link;
    }

    public function setLink(string $link): self
    {
        $this->link = $link;

        return $this;
    }

    public function getGuid(): string
    {
        return $this->guid;
    }

    public function setGuid(string $guid): self
    {
        $this->guid = $guid;

        return $this;
    }

    public function getCreator(): string
    {
        return $this->creator;
    }

    public function setCreator(string $creator): self
    {
        $this->creator = $creator;

        return $this;
    }

    public function getPubDate(): ?DateTimeImmutable
    {
        return $this->pubDate;
    }

    public function setPubDate(?DateTimeImmutable $pubDate): self
    {
        $this->pubDate = $pubDate;

        return $this;
    }

    public function getItunes(): ItemItunes
    {
        return $this->itunes;
    }

    public function setItunes(ItemItunes $itunes): self
    {
        $this->itunes = $itunes;

        return $this;
    }

    public function getEnclosure(): ItemEnclosure
    {
        return $this->enclosure;
    }

    public function setEnclosure(ItemEnclosure $enclosure): self
    {
        $this->enclosure = $enclosure;

        return $this;
    }
}
