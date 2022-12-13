<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider\Unsplash;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\String\UnicodeString;

final class UnsplashImageDto
{
    #[Serialize]
    private string $id;

    #[Serialize]
    private string $description;

    #[Serialize(serializedName: 'alt_description')]
    private string $altDescription;

    #[Serialize]
    private UnsplashImageUrlsDto $urls;

    #[Serialize]
    private int $width;

    #[Serialize]
    private int $height;

    #[Serialize]
    private UnsplashUserDto $user;

    #[Serialize(type: UnsplashTagDto::class)]
    private ArrayCollection $tags;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

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

    public function getAltDescription(): string
    {
        return $this->altDescription;
    }

    public function setAltDescription(string $altDescription): self
    {
        $this->altDescription = $altDescription;

        return $this;
    }

    public function getResolvedDescription(): string
    {
        return $this->getAltDescription() ?: $this->getDescription();
    }

    public function getDisplayTitle(): string
    {
        return (new UnicodeString($this->getResolvedDescription()))
            ->truncate(30, '…', false)
            ->toString() ?: $this->getId()
        ;
    }

    public function getUrls(): UnsplashImageUrlsDto
    {
        return $this->urls;
    }

    public function setUrls(UnsplashImageUrlsDto $urls): self
    {
        $this->urls = $urls;

        return $this;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function setWidth(int $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function setHeight(int $height): self
    {
        $this->height = $height;

        return $this;
    }

    public function getUser(): UnsplashUserDto
    {
        return $this->user;
    }

    public function setUser(UnsplashUserDto $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return ArrayCollection<int, UnsplashTagDto>
     */
    public function getTags(): ArrayCollection
    {
        return $this->tags;
    }

    public function setTags(ArrayCollection $tags): self
    {
        $this->tags = $tags;

        return $this;
    }
}
