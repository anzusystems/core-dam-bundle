<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\RssFeed;

final class ChannelItunes
{
    private string $image = '';
    private array $categories = [];
    private string $explicit = '';

    public function getImage(): string
    {
        return $this->image;
    }

    public function setImage(string $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function addCategory(string $category): self
    {
        $this->categories[] = $category;

        return $this;
    }

    public function getCategories(): array
    {
        return $this->categories;
    }

    public function setCategories(array $categories): self
    {
        $this->categories = $categories;

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
}
