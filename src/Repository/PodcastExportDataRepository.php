<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\PodcastExportData;

/**
 * @extends AbstractAnzuRepository<PodcastExportData>
 *
 * @method PodcastExportData|null find($id, $lockMode = null, $lockVersion = null)
 * @method PodcastExportData|null findOneBy(array $criteria, array $orderBy = null)
 */
class PodcastExportDataRepository extends AbstractAnzuRepository
{
    protected function getEntityClass(): string
    {
        return PodcastExportData::class;
    }
}
