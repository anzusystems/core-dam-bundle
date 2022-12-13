<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\RssFeed;

final class ItemEnclosure
{
    private string $url = '';
    private string $type = '';

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }
}
