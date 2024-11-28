<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\JobAuthorCurrentOptimize;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopy;

/**
 * @extends AbstractAnzuRepository<JobImageCopy>
 *
 * @method JobImageCopy|null find($id, $lockMode = null, $lockVersion = null)
 * @method JobImageCopy|null findOneBy(array $criteria, array $orderBy = null)
 */
final class JobAuthorCurrentOptimizeRepository extends AbstractAnzuRepository
{
    protected function getEntityClass(): string
    {
        return JobAuthorCurrentOptimize::class;
    }
}
