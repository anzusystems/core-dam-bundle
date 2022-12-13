<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Image\Filter;

final class QualityFilter implements ImageFilterInterface
{
    public function __construct(
        private readonly int $quality
    ) {
    }

    public function getQuality(): int
    {
        return $this->quality;
    }
}
