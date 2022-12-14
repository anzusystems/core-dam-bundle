<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider\Unsplash;

use AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider\AssetExternalProviderMetadataDto;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class UnsplashMetadataDto extends AssetExternalProviderMetadataDto
{
    private int $width;
    private int $height;
    private array $tags;
    private string $authorPortfolio;

    public static function getInstance(UnsplashImageDto $imageDto): self
    {
        return (new self())
            ->setWidth($imageDto->getWidth())
            ->setHeight($imageDto->getHeight())
            ->setTags($imageDto->getTags()->map(static fn (UnsplashTagDto $tagDto) => $tagDto->getTitle())->getValues())
            ->setAuthorName($imageDto->getUser()->getName())
            ->setAuthorPortfolio($imageDto->getUser()->getPortfolioUrl())
        ;
    }

    #[Serialize]
    public function getWidth(): int
    {
        return $this->width;
    }

    public function setWidth(int $width): self
    {
        $this->width = $width;

        return $this;
    }

    #[Serialize]
    public function getHeight(): int
    {
        return $this->height;
    }

    public function setHeight(int $height): self
    {
        $this->height = $height;

        return $this;
    }

    #[Serialize]
    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): self
    {
        $this->tags = $tags;

        return $this;
    }

    #[Serialize]
    public function getAuthorPortfolio(): string
    {
        return $this->authorPortfolio;
    }

    public function setAuthorPortfolio(string $authorPortfolio): self
    {
        $this->authorPortfolio = $authorPortfolio;

        return $this;
    }
}
