<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\RssDistribution;

/**
 * @extends AbstractAnzuRepository<RssDistribution>
 *
 * @method RssDistribution|null find($id, $lockMode = null, $lockVersion = null)
 * @method RssDistribution|null findOneBy(array $criteria, array $orderBy = null)
 */
final class RssDistributionRepository extends AbstractAnzuRepository
{
    protected function getEntityClass(): string
    {
        return RssDistribution::class;
    }
}
