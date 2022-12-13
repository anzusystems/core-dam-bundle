<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\QueryFactory;

use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\SearchDtoInterface;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface QueryFactoryInterface
{
    public function buildQuery(SearchDtoInterface $searchDto, ExtSystem $extSystem): array;

    /**
     * @template T of SearchDtoInterface
     *
     * @return array<class-string<T>>
     */
    public function getSupportedSearchDtoClasses(): array;
}
