<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class RouteUri
{
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Serialize]
    private string $path;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Serialize]
    private string $slug;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Serialize]
    private ?string $schemeAndHost;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Serialize]
    private bool $main;

    public function __construct()
    {
        $this->setPath('');
        $this->setSlug('');
        $this->setMain(false);
        $this->setSchemeAndHost(null);
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

    public function isMain(): bool
    {
        return $this->main;
    }

    public function setMain(bool $main): self
    {
        $this->main = $main;
        return $this;
    }

    public function getSchemeAndHost(): ?string
    {
        return $this->schemeAndHost;
    }

    public function setSchemeAndHost(?string $schemeAndHost): self
    {
        $this->schemeAndHost = $schemeAndHost;
        return $this;
    }
}
