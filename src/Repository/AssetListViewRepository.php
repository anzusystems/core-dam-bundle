<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\AssetListView;
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
     * Views targeted at licence groups that list at least one of the licences, with all their licences.
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

        return $this->createOrderedQueryBuilder()
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
     * Licences of the given views that some group of the view other than the excluded one still reaches.
     *
     * @param list<int> $viewIds
     * @param list<int> $licenceIds
     *
     * @return array<int, list<int>> view id => licence ids
     */
    public function findLicenceIdsReachedByOtherGroups(array $viewIds, array $licenceIds, int $excludedGroupId): array
    {
        if ([] === $viewIds || [] === $licenceIds) {
            return [];
        }

        /** @var list<array{viewId: int|string, licenceId: int|string}> $rows */
        $rows = $this->createQueryBuilder('entity')
            ->select('DISTINCT entity.id AS viewId, licence.id AS licenceId')
            ->innerJoin('entity.groups', 'reachingGroup')
            ->innerJoin('reachingGroup.licences', 'licence')
            ->where('entity.id IN (:viewIds)')
            ->andWhere('reachingGroup.id != :excludedGroupId')
            ->andWhere('licence.id IN (:licenceIds)')
            ->setParameter('viewIds', $viewIds)
            ->setParameter('licenceIds', $licenceIds)
            ->setParameter('excludedGroupId', $excludedGroupId)
            ->getQuery()
            ->getArrayResult()
        ;

        $reached = [];
        foreach ($rows as $row) {
            $reached[(int) $row['viewId']][] = (int) $row['licenceId'];
        }

        return $reached;
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
