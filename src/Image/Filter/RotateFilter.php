<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Image\Filter;

final class RotateFilter implements ImageFilterInterface
{
    public function __construct(
        private readonly float $angle
    ) {
    }

    public function getAngle(): float
    {
        return $this->angle;
    }
}
