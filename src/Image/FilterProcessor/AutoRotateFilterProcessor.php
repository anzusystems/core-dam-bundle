<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Image\FilterProcessor;

use AnzuSystems\CoreDamBundle\Image\Filter\AutoRotateFilter;
use AnzuSystems\CoreDamBundle\Image\Filter\ImageFilterInterface;

final class AutoRotateFilterProcessor extends AbstractFilterProcessor
{
    public function supportsFilter(): string
    {
        return AutoRotateFilter::class;
    }

    public static function getDefaultKeyName(): string
    {
        return AutoRotateFilter::class;
    }

    public function applyFilter(ImageFilterInterface $filter): void
    {
        if (false === ($filter instanceof AutoRotateFilter)) {
            return;
        }

        if ($filter->isAutorotate()) {
            $this->imageManipulator->autorotate();
        }
    }
}
