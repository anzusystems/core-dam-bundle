<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CommonBundle\Repository\AbstractAnzuRepository as BaseAbstractAnzuRepository;
use AnzuSystems\Contracts\Entity\Interfaces\BaseIdentifiableInterface;
use AnzuSystems\CoreDamBundle\Elasticsearch\RebuildIndexConfig;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;

/**
 * @template T of BaseIdentifiableInterface
 *
 * @method BaseIdentifiableInterface|null find($id, $lockMode = null, $lockVersion = null)
 * @method BaseIdentifiableInterface|null findOneBy(array $criteria, array $orderBy = null)
 */
abstract class AbstractAnzuRepository extends BaseAbstractAnzuRepository
{
    protected ?int $extSystemIdReindexCache = null;

    /**
     * @return ArrayCollection<int, T>
     */
    public function getAllForIndexRebuild(RebuildIndexConfig $config): ArrayCollection
    {
        return new ArrayCollection(
            $this->getAllForIndexRebuildQuery($config)
                ->orderBy('entity.id', Criteria::ASC)
                ->setMaxResults($config->getBatchSize())
                ->getQuery()->getResult()
        );
    }

    /**
     * @throws NonUniqueResultException
     */
    public function getAllCountForIndexRebuild(RebuildIndexConfig $config): int
    {
        try {
            return $this
                ->getAllForIndexRebuildQuery($config)
                ->select('COUNT(entity)')
                ->getQuery()
                ->getSingleScalarResult();
        } catch (NoResultException) {
            return 0;
        }
    }

    /**
     * @throws NonUniqueResultException
     */
    public function getMaxIdForIndexRebuild(RebuildIndexConfig $config): string
    {
        try {
            return $this
                ->getAllForIndexRebuildQuery($config)
                ->select('entity.id')
                ->orderBy('entity.id', Criteria::DESC)
                ->setMaxResults(1)
                ->getQuery()
                ->getSingleScalarResult();
        } catch (NoResultException) {
            return '';
        }
    }

    public function getAll(string $idFrom = '', string $idUntil = '', int $limit = 500): Collection
    {
        return new ArrayCollection(
            $this->getAllQuery('entity', $idFrom, $idUntil)
                ->orderBy('entity.id', Order::Ascending->value)
                ->setMaxResults($limit)
                ->getQuery()->getResult()
        );
    }

    public function getAllCount(string $idFrom = '', string $idUntil = ''): int
    {
        try {
            return (int) $this->getAllQuery('count(entity)', $idFrom, $idUntil)
                ->getQuery()->getSingleScalarResult();
        } catch (ORMException) {
            return 0;
        }
    }

    /**
     * @return class-string<T>
     */
    abstract protected function getEntityClass(): string;

    protected function appendRebuildIndexQueryForExtSystem(QueryBuilder $queryBuilder, int $extSystemId): QueryBuilder
    {
        return $queryBuilder
            ->andWhere('IDENTITY(entity.extSystem) = :extSystemId')
            ->setParameter('extSystemId', $extSystemId);
    }

    protected function getAllQuery(
        string $select = 'entity',
        string $idFrom = '',
        string $idUntil = '',
    ): QueryBuilder {
        $query = $this->getEntityManager()->createQueryBuilder()
            ->select($select)
            ->from($this->getEntityClass(), 'entity')
        ;

        if (false === ('' === $idFrom)) {
            $query->andWhere('entity.id >= :idFrom')
                ->setParameter('idFrom', $idFrom, Types::STRING);
        }
        if (false === ('' === $idUntil)) {
            $query->andWhere('entity.id <= :idUntil')
                ->setParameter('idUntil', $idUntil, Types::STRING);
        }

        return $query;
    }

    private function getAllForIndexRebuildQuery(RebuildIndexConfig $config): QueryBuilder
    {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder()
            ->select('entity')
            ->from($this->getEntityClass(), 'entity')
        ;

        if ($config->hasExtSystemSlug()) {
            $this->extSystemIdReindexCache ??= $this->getEntityManager()
                ->getRepository(ExtSystem::class)
                ->getIdBySlug($config->getExtSystemSlug());
            $queryBuilder = $this->appendRebuildIndexQueryForExtSystem($queryBuilder, $this->extSystemIdReindexCache);
        }
        if ($config->hasIdFrom() || $config->hasLastProcessedId()) {
            $idFromCompareCharacter = $config->hasLastProcessedId() ? '>' : '>=';
            $idFrom = $config->hasLastProcessedId() ? $config->getLastProcessedId() : $config->getIdFrom();
            $queryBuilder->andWhere("entity.id {$idFromCompareCharacter} :idFrom")
                ->setParameter('idFrom', $idFrom);
        }
        if ($config->hasIdUntil()) {
            $queryBuilder->andWhere('entity.id <= :idUntil')
                ->setParameter('idUntil', $config->getResolvedMaxId());
        }

        return $queryBuilder;
    }
}
