<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Image\Filter;

final class FilterStack
{
    public function __construct(
        private array $filters = []
    ) {
    }

    public function addFilter(ImageFilterInterface $filter): self
    {
        $this->filters[] = $filter;

        return $this;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }
}
