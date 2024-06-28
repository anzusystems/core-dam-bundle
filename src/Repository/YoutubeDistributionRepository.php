<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\YoutubeDistribution;

/**
 * @extends AbstractAnzuRepository<YoutubeDistribution>
 *
 * @method YoutubeDistribution|null find($id, $lockMode = null, $lockVersion = null)
 * @method YoutubeDistribution|null findOneBy(array $criteria, array $orderBy = null)
 */
final class YoutubeDistributionRepository extends DistributionRepository
{
    protected function getEntityClass(): string
    {
        return YoutubeDistribution::class;
    }
}
