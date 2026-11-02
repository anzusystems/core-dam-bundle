<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\AssetListView;
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

    public function isLicenceUsed(AssetLicence $licence): bool
    {
        return (int) $this->createQueryBuilder('entity')
            ->select('COUNT(entity.id)')
            ->leftJoin('entity.licences', 'licence')
            ->where('licence = :licence OR entity.uploadLicence = :licence')
            ->setParameter('licence', $licence)
            ->getQuery()
            ->getSingleScalarResult() > App::ZERO;
    }

    protected function getEntityClass(): string
    {
        return AssetListView::class;
    }

    private function createOrderedQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('entity')
            ->orderBy('entity.position', App::ORDER_ASC)
            ->addOrderBy('entity.id', App::ORDER_ASC)
        ;
    }
}
