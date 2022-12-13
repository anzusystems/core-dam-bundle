<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Image\FilterProcessor\Stack;

use AnzuSystems\CoreDamBundle\Exception\ImageManipulatorException;
use AnzuSystems\CoreDamBundle\Image\Filter\ImageFilterInterface;
use AnzuSystems\CoreDamBundle\Image\FilterProcessor\FilterProcessorInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class FilterProcessorStack
{
    private iterable $filterProcessors;

    public function __construct(
        #[TaggedIterator(tag: FilterProcessorInterface::class, indexAttribute: 'key')]
        iterable $filterProcessors,
    ) {
        $this->filterProcessors = $filterProcessors;
    }

    /**
     * @throws ImageManipulatorException
     */
    public function getFilterProcessor(ImageFilterInterface $filter): FilterProcessorInterface
    {
        foreach ($this->filterProcessors as $key => $processor) {
            if ($key === $filter::class) {
                return $processor;
            }
        }

        throw new ImageManipulatorException(ImageManipulatorException::ERROR_PROCESSOR_NOT_FOUND);
    }
}
