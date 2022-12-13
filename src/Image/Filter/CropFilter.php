<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Image\Filter;

final class CropFilter implements ImageFilterInterface
{
    public function __construct(
        private readonly int $pointX,
        private readonly int $pointY,
        private readonly int $width,
        private readonly int $height
    ) {
    }

    public function getPointX(): int
    {
        return $this->pointX;
    }

    public function getPointY(): int
    {
        return $this->pointY;
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
