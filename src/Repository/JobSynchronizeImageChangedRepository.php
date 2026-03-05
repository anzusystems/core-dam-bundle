<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\JobSynchronizeImageChanged;

/**
 * @extends AbstractAnzuRepository<JobSynchronizeImageChanged>
 *
 * @method JobSynchronizeImageChanged|null find($id, $lockMode = null, $lockVersion = null)
 * @method JobSynchronizeImageChanged|null findOneBy(array $criteria, array $orderBy = null)
 */
final class JobSynchronizeImageChangedRepository extends AbstractAnzuRepository
{
    protected function getEntityClass(): string
    {
        return JobSynchronizeImageChanged::class;
    }
}
