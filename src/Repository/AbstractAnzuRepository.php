<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CommonBundle\Repository\AbstractAnzuRepository as BaseAbstractAnzuRepository;
use AnzuSystems\Contracts\Entity\Interfaces\IdentifiableInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;

/**
 * @template T of IdentifiableInterface
 *
 * @method IdentifiableInterface|null find($id, $lockMode = null, $lockVersion = null)
 * @method IdentifiableInterface|null findOneBy(array $criteria, array $orderBy = null)
 */
abstract class AbstractAnzuRepository extends BaseAbstractAnzuRepository
{
    protected const UNIQUE_PROPERTIES = [];
    private const ALWAYS_UNIQUE_PROPERTIES = ['id'];

    public function getAll(int $idFrom = 0, int $idUntil = 0, int $limit = 500): Collection
    {
        return new ArrayCollection(
            $this->getAllQuery($idFrom, $idUntil)
                ->setMaxResults($limit)
                ->getQuery()->getResult()
        );
    }

    public function getExisting(array $criteria): ?IdentifiableInterface
    {
        return $this->findOneBy($criteria);
    }

    public function getUniqueProperties(): array
    {
        return array_merge(static::UNIQUE_PROPERTIES, self::ALWAYS_UNIQUE_PROPERTIES);
    }

    /**
     * @return class-string<T>
     */
    abstract protected function getEntityClass(): string;

    protected function getAllQuery(
        int $idFrom = 0,
        int $idUntil = 0,
        string $select = 'entity'
    ): QueryBuilder {
        $query = $this->getEntityManager()->createQueryBuilder()
            ->select($select)
            ->from($this->getEntityClass(), 'entity')
        ;

        if ($idFrom) {
            $query->andWhere('entity.id >= :idFrom')
                ->setParameter('idFrom', $idFrom, Types::INTEGER);
        }
        if ($idUntil) {
            $query->andWhere('entity.id <= :idUntil')
                ->setParameter('idUntil', $idUntil, Types::INTEGER);
        }

        return $query;
    }
}
