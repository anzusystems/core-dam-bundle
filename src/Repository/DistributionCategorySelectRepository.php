<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\DistributionCategorySelect;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;

/**
 * @extends AbstractAnzuRepository<DistributionCategorySelect>
 *
 * @method DistributionCategorySelect|null find($id, $lockMode = null, $lockVersion = null)
 * @method DistributionCategorySelect|null findOneBy(array $criteria, array $orderBy = null)
 */
final class DistributionCategorySelectRepository extends AbstractAnzuRepository
{
    public function findOneForExtSystemService(
        string $serviceSlug,
        ExtSystem $extSystem,
        AssetType $type,
    ): ?DistributionCategorySelect {
        return $this->findOneBy([
            'serviceSlug' => $serviceSlug,
            'extSystem' => $extSystem,
            'type' => $type,
        ]);
    }

    /**
     * @return list<DistributionCategorySelect>
     */
    public function getAllForExtSystemAndType(ExtSystem $extSystem, AssetType $type): array
    {
        return $this->createQueryBuilder('categorySelect')
            ->where('IDENTITY(categorySelect.extSystem) = :extSystemId')
            ->andWhere('categorySelect.type = :assetType')
            ->setParameter('extSystemId', (int) $extSystem->getId())
            ->setParameter('assetType', $type)
            ->getQuery()
            ->getResult()
        ;
    }

    protected function getEntityClass(): string
    {
        return DistributionCategorySelect::class;
    }
}
