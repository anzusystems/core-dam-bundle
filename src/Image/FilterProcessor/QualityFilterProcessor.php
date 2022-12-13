<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Image\FilterProcessor;

use AnzuSystems\CoreDamBundle\Image\Filter\ImageFilterInterface;
use AnzuSystems\CoreDamBundle\Image\Filter\QualityFilter;

final class QualityFilterProcessor extends AbstractFilterProcessor
{
    public function supportsFilter(): string
    {
        return QualityFilter::class;
    }

    public static function getDefaultKeyName(): string
    {
        return QualityFilter::class;
    }

    public function applyFilter(ImageFilterInterface $filter): void
    {
        if (false === ($filter instanceof QualityFilter)) {
            return;
        }

        $this->imageManipulator->setQuality(
            $filter->getQuality()
        );
    }
}
