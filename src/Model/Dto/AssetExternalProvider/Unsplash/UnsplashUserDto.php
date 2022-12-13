<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider\Unsplash;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class UnsplashUserDto
{
    #[Serialize]
    private string $name;

    #[Serialize(serializedName: 'portfolio_url')]
    private string $portfolioUrl;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getPortfolioUrl(): string
    {
        return $this->portfolioUrl;
    }

    public function setPortfolioUrl(string $portfolioUrl): self
    {
        $this->portfolioUrl = $portfolioUrl;

        return $this;
    }
}
