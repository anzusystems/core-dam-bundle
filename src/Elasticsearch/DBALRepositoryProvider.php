<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch;

use AnzuSystems\CoreDamBundle\Elasticsearch\Exception\AnzuElasticSearchException;
use AnzuSystems\CoreDamBundle\Elasticsearch\Repository\DBALIndexableRepositoryInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\DBALIndexableInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;

final class DBALRepositoryProvider
{
    private ContainerInterface $dbalRepositories;

    public function __construct(
        #[TaggedLocator(tag: DBALIndexableRepositoryInterface::class)]
        ContainerInterface $dbalRepositories,
    ) {
        $this->dbalRepositories = $dbalRepositories;
    }

    /**
     * @param class-string<DBALIndexableInterface> $className
     *
     * @throws AnzuElasticSearchException
     */
    public function getRepository(string $className): DBALIndexableRepositoryInterface
    {
        try {
            return $this->dbalRepositories->get($className::getRepositoryClassName());
        } catch (ContainerExceptionInterface|NotFoundExceptionInterface $e) {
            throw new AnzuElasticSearchException(
                message: 'dbal_repository_not_found',
                detail: sprintf('DBALRepository for class (%s) not found', $className::getRepositoryClassName()),
                previous: $e
            );
        }
    }

}
