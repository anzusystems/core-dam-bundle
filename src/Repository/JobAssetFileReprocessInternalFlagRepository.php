<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\JobAssetFileReprocessInternalFlag;

/**
 * @extends AbstractAnzuRepository<JobAssetFileReprocessInternalFlag>
 *
 * @method JobAssetFileReprocessInternalFlag|null find($id, $lockMode = null, $lockVersion = null)
 * @method JobAssetFileReprocessInternalFlag|null findOneBy(array $criteria, array $orderBy = null)
 */
final class JobAssetFileReprocessInternalFlagRepository extends AbstractAnzuRepository
{
    protected function getEntityClass(): string
    {
        return JobAssetFileReprocessInternalFlag::class;
    }
}
