<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch;

use AnzuSystems\CoreDamBundle\Elasticsearch\IndexFactory\IndexFactoryInterface;
use AnzuSystems\CoreDamBundle\Exception\DomainException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class IndexFactoryProvider
{
    /**
     * @var iterable<string<IndexFactoryInterface>, IndexFactoryInterface>
     */
    private iterable $indexFactories;

    public function __construct(
        #[TaggedIterator(tag: IndexFactoryInterface::class, indexAttribute: 'key')]
        iterable $indexFactories,
    ) {
        $this->indexFactories = $indexFactories;
    }

    /**
     * @psalm-param class-string $className
     */
    public function getIndexFactory(string $className): IndexFactoryInterface
    {
        foreach ($this->indexFactories as $key => $queryFactory) {
            if ($key === $className || is_subclass_of($className, $key)) {
                return $queryFactory;
            }
        }

        throw new DomainException(
            sprintf(
                'Missing index factory for entity (%s)',
                $className
            )
        );
    }
}
