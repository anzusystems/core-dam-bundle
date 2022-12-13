<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\DistributionCategoryOption;

/**
 * @extends AbstractAnzuRepository<DistributionCategoryOption>
 *
 * @method DistributionCategoryOption|null find($id, $lockMode = null, $lockVersion = null)
 * @method DistributionCategoryOption|null findOneBy(array $criteria, array $orderBy = null)
 */
final class DistributionCategoryOptionRepository extends AbstractAnzuRepository
{
    protected function getEntityClass(): string
    {
        return DistributionCategoryOption::class;
    }
}
