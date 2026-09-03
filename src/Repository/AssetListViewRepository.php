<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\AssetLicenceGroup;
use AnzuSystems\CoreDamBundle\Entity\AssetListView;
use Doctrine\DBAL\ArrayParameterType;
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

    /**
     * Drops the licences from every targeted view that no longer reaches them through any of its other groups,
     * and nulls the view's upload licence when it no longer belongs to the view's remaining licences.
     * Excluding the edited group makes the result independent of whether its own change is already flushed.
     *
     * @param list<int> $licenceIds
     *
     * @throws Exception
     */
    public function removeLicencesUnreachableByOtherGroups(array $licenceIds, AssetLicenceGroup $excludedGroup): void
    {
        if ([] === $licenceIds) {
            return;
        }

        $this->getEntityManager()->getConnection()->executeStatement(
            <<<SQL
                DELETE licence_in_view
                FROM asset_licence_in_list_view AS licence_in_view
                WHERE licence_in_view.asset_licence_id IN (:licenceIds)
                    AND EXISTS (
                        SELECT 1
                        FROM licence_group_in_list_view AS any_group
                        WHERE any_group.asset_list_view_id = licence_in_view.asset_list_view_id
                    )
                    AND NOT EXISTS (
                        SELECT 1
                        FROM licence_group_in_list_view AS reaching_group
                        INNER JOIN asset_licence_in_group AS licence_in_group
                            ON licence_in_group.asset_licence_group_id = reaching_group.asset_licence_group_id
                        WHERE reaching_group.asset_list_view_id = licence_in_view.asset_list_view_id
                            AND reaching_group.asset_licence_group_id != :excludedGroupId
                            AND licence_in_group.asset_licence_id = licence_in_view.asset_licence_id
                    )
                SQL,
            [
                'licenceIds' => $licenceIds,
                'excludedGroupId' => $excludedGroup->getId(),
            ],
            [
                'licenceIds' => ArrayParameterType::INTEGER,
            ]
        );

        $this->getEntityManager()->getConnection()->executeStatement(
            <<<SQL
                UPDATE asset_list_view AS view
                SET view.upload_licence_id = NULL
                WHERE view.upload_licence_id IN (:licenceIds)
                    AND NOT EXISTS (
                        SELECT 1
                        FROM asset_licence_in_list_view AS licence_in_view
                        WHERE licence_in_view.asset_list_view_id = view.id
                            AND licence_in_view.asset_licence_id = view.upload_licence_id
                    )
                SQL,
            [
                'licenceIds' => $licenceIds,
            ],
            [
                'licenceIds' => ArrayParameterType::INTEGER,
            ]
        );
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
