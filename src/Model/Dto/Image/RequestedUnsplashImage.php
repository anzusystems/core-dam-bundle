<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Image;

final class RequestedUnsplashImage
{
    public function __construct(
        private readonly int $width,
        private readonly int $height,
        private readonly string $keyword,
    ) {
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getKeyword(): string
    {
        return $this->keyword;
    }
}
