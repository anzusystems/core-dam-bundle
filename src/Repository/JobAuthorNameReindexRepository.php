<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\JobAuthorNameReindex;

/**
 * @extends AbstractAnzuRepository<JobAuthorNameReindex>
 *
 * @method JobAuthorNameReindex|null find($id, $lockMode = null, $lockVersion = null)
 * @method JobAuthorNameReindex|null findOneBy(array $criteria, array $orderBy = null)
 */
final class JobAuthorNameReindexRepository extends AbstractAnzuRepository
{
    protected function getEntityClass(): string
    {
        return JobAuthorNameReindex::class;
    }
}
