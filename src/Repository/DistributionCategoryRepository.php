<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\DistributionCategory;

/**
 * @extends AbstractAnzuRepository<DistributionCategory>
 *
 * @method DistributionCategory|null find($id, $lockMode = null, $lockVersion = null)
 * @method DistributionCategory|null findOneBy(array $criteria, array $orderBy = null)
 */
final class DistributionCategoryRepository extends AbstractAnzuRepository
{
    protected function getEntityClass(): string
    {
        return DistributionCategory::class;
    }
}
