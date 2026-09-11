<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\AssetListView;
use Doctrine\DBAL\Exception;
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

    /**
     * @throws Exception
     */
    public function countWithoutLicences(): int
    {
        return (int) $this->getEntityManager()->getConnection()->fetchOne(
            <<<SQL
                SELECT COUNT(*)
                FROM asset_list_view AS view
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM asset_licence_in_list_view AS licence_in_view
                    WHERE licence_in_view.asset_list_view_id = view.id
                )
                SQL
        );
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
