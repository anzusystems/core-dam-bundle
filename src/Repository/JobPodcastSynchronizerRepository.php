<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\JobPodcastSynchronizer;

/**
 * @extends AbstractAnzuRepository<JobPodcastSynchronizer>
 *
 * @method JobPodcastSynchronizer|null find($id, $lockMode = null, $lockVersion = null)
 * @method JobPodcastSynchronizer|null findOneBy(array $criteria, array $orderBy = null)
 */
final class JobPodcastSynchronizerRepository extends AbstractAnzuRepository
{
    protected function getEntityClass(): string
    {
        return JobPodcastSynchronizer::class;
    }
}
