<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class AudioPublicLink
{
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $path;

    #[ORM\Column(type: Types::STRING, length: 128)]
    private string $slug;

    #[ORM\Column(name: 'is_public', type: Types::BOOLEAN)]
    private bool $public;

    public function __construct()
    {
        $this->setPath('');
        $this->setSlug('');
        $this->setPublic(false);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): self
    {
        $this->public = $public;

        return $this;
    }
}
