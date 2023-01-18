<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CommonBundle\Repository\AbstractAnzuRepository as BaseAbstractAnzuRepository;
use AnzuSystems\Contracts\Entity\Interfaces\BaseIdentifiableInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
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
    /**
     * @return ArrayCollection<int, T>
     */
    public function getAll(int|string $idFrom = 0, int|string $idUntil = 0, int $limit = 500): ArrayCollection
    {
        return new ArrayCollection(
            $this->getAllQuery($idFrom, $idUntil)
                ->setMaxResults($limit)
                ->getQuery()->getResult()
        );
    }

    public function getAllCount(int|string $idFrom = 0, int|string $idUntil = 0): int
    {
        return $this
            ->getAllQuery($idFrom, $idUntil)
            ->select('COUNT(entity)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function getMaxId(): int|string
    {
        return $this
            ->createQueryBuilder('entity')
            ->select('entity.id')
            ->setMaxResults(1)
            ->orderBy('entity.id', Criteria::DESC)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return class-string<T>
     */
    abstract protected function getEntityClass(): string;

    protected function getAllQuery(
        int|string $idFrom = 0,
        int|string $idUntil = 0,
    ): QueryBuilder {
        $query = $this->getEntityManager()->createQueryBuilder()
            ->select('entity')
            ->from($this->getEntityClass(), 'entity')
        ;

        if ($idFrom) {
            $query->andWhere('entity.id >= :idFrom')
                ->setParameter('idFrom', $idFrom);
        }
        if ($idUntil) {
            $query->andWhere('entity.id <= :idUntil')
                ->setParameter('idUntil', $idUntil);
        }

        return $query;
    }
}
