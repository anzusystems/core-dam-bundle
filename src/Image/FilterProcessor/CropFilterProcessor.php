<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Image\FilterProcessor;

use AnzuSystems\CoreDamBundle\Image\Filter\CropFilter;
use AnzuSystems\CoreDamBundle\Image\Filter\ImageFilterInterface;

final class CropFilterProcessor extends AbstractFilterProcessor
{
    public function supportsFilter(): string
    {
        return CropFilter::class;
    }

    public static function getDefaultKeyName(): string
    {
        return CropFilter::class;
    }

    public function applyFilter(ImageFilterInterface $filter): void
    {
        if (false === ($filter instanceof CropFilter)) {
            return;
        }

        $this->imageManipulator->crop(
            $filter->getPointX(),
            $filter->getPointY(),
            $filter->getWidth(),
            $filter->getHeight()
        );
    }
}
