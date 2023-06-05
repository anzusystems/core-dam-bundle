<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\JwDistribution;

/**
 * @extends AbstractAnzuRepository<JwDistribution>
 *
 * @method JwDistribution|null find($id, $lockMode = null, $lockVersion = null)
 * @method JwDistribution|null findOneBy(array $criteria, array $orderBy = null)
 */
final class JwDistributionRepository extends DistributionRepository
{
    protected function getEntityClass(): string
    {
        return JwDistribution::class;
    }
}
