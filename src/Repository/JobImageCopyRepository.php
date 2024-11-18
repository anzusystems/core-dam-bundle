<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\JobImageCopy;

/**
 * @extends AbstractAnzuRepository<JobImageCopy>
 *
 * @method JobImageCopy|null find($id, $lockMode = null, $lockVersion = null)
 * @method JobImageCopy|null findOneBy(array $criteria, array $orderBy = null)
 */
final class JobImageCopyRepository extends AbstractAnzuRepository
{
    protected function getEntityClass(): string
    {
        return JobImageCopy::class;
    }
}
