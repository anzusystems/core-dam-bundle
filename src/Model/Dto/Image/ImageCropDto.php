<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Image;

final class ImageCropDto
{
    public function __construct(
        private readonly int $pointX,
        private readonly int $pointY,
        private readonly int $width,
        private readonly int $height,
        private readonly int $requestWidth,
        private readonly int $requestHeight,
        private readonly int $quality,
    ) {
    }

    public function __toString(): string
    {
        return sprintf(
            '%s_%s_%s_%s_%s_%s_%s',
            $this->pointX,
            $this->pointY,
            $this->width,
            $this->height,
            $this->requestWidth,
            $this->requestHeight,
            $this->quality
        );
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

    public function getRequestWidth(): int
    {
        return $this->requestWidth;
    }

    public function getRequestHeight(): int
    {
        return $this->requestHeight;
    }

    public function getQuality(): int
    {
        return $this->quality;
    }

    public function getFilePath(): string
    {
        return sprintf(
            '%s_%s_%s_%s_%s_%s_%s',
            $this->pointX,
            $this->pointY,
            $this->width,
            $this->height,
            $this->requestWidth,
            $this->requestHeight,
            $this->quality
        );
    }
}
