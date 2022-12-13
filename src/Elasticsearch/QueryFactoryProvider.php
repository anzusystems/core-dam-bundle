<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch;

use AnzuSystems\CoreDamBundle\Elasticsearch\QueryFactory\QueryFactoryInterface;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\SearchDtoInterface;
use AnzuSystems\CoreDamBundle\Exception\DomainException;
use Doctrine\Common\Util\ClassUtils;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class QueryFactoryProvider
{
    /**
     * @var iterable<integer, QueryFactoryInterface>
     */
    private iterable $queryFactories;

    public function __construct(
        #[TaggedIterator(tag: QueryFactoryInterface::class)]
        iterable $queryFactories,
    ) {
        $this->queryFactories = $queryFactories;
    }

    public function getQueryFactory(SearchDtoInterface $searchDto): QueryFactoryInterface
    {
        foreach ($this->queryFactories as $queryFactory) {
            if (in_array(ClassUtils::getRealClass($searchDto::class), $queryFactory->getSupportedSearchDtoClasses(), true)) {
                return $queryFactory;
            }
        }

        throw new DomainException(
            sprintf(
                'Missing query factory for search dto (%s)',
                $searchDto::class
            )
        );
    }
}
