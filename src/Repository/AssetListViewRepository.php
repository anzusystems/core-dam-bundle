<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\AssetListView;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends AbstractAnzuRepository<AssetListView>
 *
 * @method AssetListView|null find($id, $lockMode = null, $lockVersion = null)
 * @method AssetListView|null findOneBy($id, $lockMode = null, $lockVersion = null)
 */
final class AssetListViewRepository extends AbstractAnzuRepository
{
    /**
     * @param list<int> $groupIds
     *
     * @return list<AssetListView>
     */
    public function findByGroups(array $groupIds): array
    {
        if ([] === $groupIds) {
            return [];
        }

        return $this->createOrderedQueryBuilder()
            ->distinct()
            ->innerJoin('entity.groups', 'groups')
            ->leftJoin('entity.licences', 'licences')
            ->addSelect('licences')
            ->andWhere('groups.id IN (:groupIds)')
            ->setParameter('groupIds', $groupIds)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Views without targeting apply to everyone in their external system.
     *
     * @return list<AssetListView>
     */
    public function findWithoutGroups(): array
    {
        return $this->createOrderedQueryBuilder()
            ->leftJoin('entity.groups', 'groups')
            ->leftJoin('entity.licences', 'licences')
            ->addSelect('licences')
            ->andWhere('groups.id IS NULL')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Views with at least one group that list at least one of the licences, with all their licences.
     *
     * @param list<int> $licenceIds
     *
     * @return list<AssetListView>
     */
    public function findTargetedByLicences(array $licenceIds): array
    {
        if ([] === $licenceIds) {
            return [];
        }

        return $this->createQueryBuilder('entity')
            ->distinct()
            ->innerJoin('entity.groups', 'groups')
            ->leftJoin('entity.licences', 'licences')
            ->addSelect('licences')
            ->innerJoin(
                join: 'entity.licences',
                alias: 'targetedLicence',
                conditionType: Expr\Join::WITH,
                condition: 'targetedLicence.id IN (:licenceIds)'
            )
            ->setParameter('licenceIds', $licenceIds)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @param list<int> $licenceIds
     *
     * @return list<AssetListView>
     */
    public function findByUploadLicenceIds(array $licenceIds): array
    {
        if ([] === $licenceIds) {
            return [];
        }

        return $this->createQueryBuilder('entity')
            ->where('IDENTITY(entity.uploadLicence) IN (:licenceIds)')
            ->setParameter('licenceIds', $licenceIds)
            ->getQuery()
            ->getResult()
        ;
    }

    protected function getEntityClass(): string
    {
        return AssetListView::class;
    }

    private function createOrderedQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('entity')
            ->orderBy('entity.position', Order::Ascending->value)
            ->addOrderBy('entity.id', Order::Ascending->value)
        ;
    }
}
