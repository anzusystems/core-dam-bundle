<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Image\FilterProcessor;

use AnzuSystems\CoreDamBundle\Image\Filter\ImageFilterInterface;
use AnzuSystems\CoreDamBundle\Image\Filter\ResizeFilter;

final class ResizeFilterProcessor extends AbstractFilterProcessor
{
    public function supportsFilter(): string
    {
        return ResizeFilter::class;
    }

    public static function getDefaultKeyName(): string
    {
        return ResizeFilter::class;
    }

    public function applyFilter(ImageFilterInterface $filter): void
    {
        if (false === ($filter instanceof ResizeFilter)) {
            return;
        }

        $this->imageManipulator->resize($filter->getWidth(), $filter->getHeight());
    }
}
