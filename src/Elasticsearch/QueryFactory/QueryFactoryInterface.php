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
     * @return array<class-string<SearchDtoInterface>>
     */
    public function getSupportedSearchDtoClasses(): array;
}
