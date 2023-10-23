<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Image\Embeds;

use AnzuSystems\CoreDamBundle\Entity\Embeds\ImageAttributes;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class ImageAttributesAdmDto
{
    #[Serialize]
    private int $ratioWidth;

    #[Serialize]
    private int $ratioHeight;

    #[Serialize]
    private int $width;

    #[Serialize]
    private int $height;

    #[Serialize]
    private int $rotation;

    #[Serialize]
    private string $mostDominantColor;

    #[Serialize]
    private bool $animated;

    public static function getInstance(ImageAttributes $attributes): self
    {
        return (new self())
            ->setRatioWidth($attributes->getRatioWidth())
            ->setRatioHeight($attributes->getRatioHeight())
            ->setWidth($attributes->getWidth())
            ->setHeight($attributes->getHeight())
            ->setRotation($attributes->getRatioWidth())
            ->setMostDominantColor($attributes->getMostDominantColor()->toString())
            ->setAnimated($attributes->isAnimated())
        ;
    }

    public function getRatioWidth(): int
    {
        return $this->ratioWidth;
    }

    public function setRatioWidth(int $ratioWidth): self
    {
        $this->ratioWidth = $ratioWidth;

        return $this;
    }

    public function getRatioHeight(): int
    {
        return $this->ratioHeight;
    }

    public function setRatioHeight(int $ratioHeight): self
    {
        $this->ratioHeight = $ratioHeight;

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

    public function getRotation(): int
    {
        return $this->rotation;
    }

    public function setRotation(int $rotation): self
    {
        $this->rotation = $rotation;

        return $this;
    }

    public function getMostDominantColor(): string
    {
        return $this->mostDominantColor;
    }

    public function setMostDominantColor(string $mostDominantColor): self
    {
        $this->mostDominantColor = $mostDominantColor;

        return $this;
    }

    public function isAnimated(): bool
    {
        return $this->animated;
    }

    public function setAnimated(bool $animated): self
    {
        $this->animated = $animated;
        return $this;
    }
}
