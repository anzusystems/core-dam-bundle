<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Image\FilterProcessor;

use AnzuSystems\CoreDamBundle\Image\Filter\ImageFilterInterface;
use AnzuSystems\CoreDamBundle\Image\Filter\RotateFilter;

final class RotateFilterProcessor extends AbstractFilterProcessor
{
    public function supportsFilter(): string
    {
        return RotateFilter::class;
    }

    public static function getDefaultKeyName(): string
    {
        return RotateFilter::class;
    }

    public function applyFilter(ImageFilterInterface $filter): void
    {
        if (false === ($filter instanceof RotateFilter)) {
            return;
        }

        $this->imageManipulator->rotate($filter->getAngle());
    }
}
