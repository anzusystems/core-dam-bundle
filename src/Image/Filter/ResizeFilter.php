<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Image\Filter;

final class ResizeFilter implements ImageFilterInterface
{
    public function __construct(
        private readonly int $width,
        private readonly int $height
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
}
