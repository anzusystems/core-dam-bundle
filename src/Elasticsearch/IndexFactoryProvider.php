<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch;

use AnzuSystems\CoreDamBundle\Elasticsearch\Exception\AnzuElasticSearchException;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexFactory\DBALIndexFactoryInterface;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexFactory\IndexFactoryInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\DBALIndexableInterface;
use AnzuSystems\CoreDamBundle\Exception\DomainException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

final class IndexFactoryProvider
{
    /**
     * @var iterable<class-string<IndexFactoryInterface>, IndexFactoryInterface>
     */
    private iterable $indexFactories;
    private ContainerInterface $dbalIndexFactories;

    public function __construct(
        #[AutowireIterator(tag: IndexFactoryInterface::class, indexAttribute: 'key')]
        iterable $indexFactories,
        #[AutowireLocator(DBALIndexFactoryInterface::class)]
        ContainerInterface $dbalIndexFactories,
    ) {
        $this->indexFactories = $indexFactories;
        $this->dbalIndexFactories = $dbalIndexFactories;
    }

    /**
     * @param class-string<DBALIndexableInterface> $className
     *
     * @throws AnzuElasticSearchException
     */
    public function getDBALIndexFactory(string $className): DBALIndexFactoryInterface
    {
        try {
            return $this->dbalIndexFactories->get($className::getDBALIndexFactoryClassName());
        } catch (ContainerExceptionInterface|NotFoundExceptionInterface $e) {
            throw new AnzuElasticSearchException(
                message: 'dbal_index_factory_not_found',
                detail: sprintf('Index factory for class (%s) not found', $className::getDBALIndexFactoryClassName()),
                previous: $e
            );
        }
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
