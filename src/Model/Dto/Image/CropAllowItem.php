<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Image;

final class CropAllowItem
{
    public function __construct(
        private readonly int $width,
        private readonly int $height,
        private readonly string $title = '',
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

    public function getTitle(): string
    {
        return $this->title;
    }
}
