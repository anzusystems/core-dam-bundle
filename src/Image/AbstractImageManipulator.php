<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Image;

use AnzuSystems\CoreDamBundle\Exception\ImageManipulatorException;
use AnzuSystems\CoreDamBundle\Image\Filter\FilterStack;
use AnzuSystems\CoreDamBundle\Image\FilterProcessor\Stack\FilterProcessorStack;

abstract class AbstractImageManipulator implements ImageManipulatorInterface
{
    public function __construct(
        private readonly FilterProcessorStack $filterProcessorStack
    ) {
    }

    /**
     * @throws ImageManipulatorException
     */
    public function applyFilterStack(FilterStack $filterStack): void
    {
        foreach ($filterStack->getFilters() as $filter) {
            $this->filterProcessorStack->getFilterProcessor($filter)->applyFilter($filter);
        }
    }
}
