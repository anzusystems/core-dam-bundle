<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Image\Filter;

final class AutoRotateFilter implements ImageFilterInterface
{
    public function __construct(
        private readonly bool $autorotate
    ) {
    }

    public function isAutorotate(): bool
    {
        return $this->autorotate;
    }
}
