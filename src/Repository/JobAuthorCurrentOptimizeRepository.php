<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CommonBundle\Model\Enum\JobStatus;
use AnzuSystems\CoreDamBundle\Entity\JobAuthorCurrentOptimize;

/**
 * @extends AbstractAnzuRepository<JobAuthorCurrentOptimize>
 *
 * @method JobAuthorCurrentOptimize|null find($id, $lockMode = null, $lockVersion = null)
 * @method JobAuthorCurrentOptimize|null findOneBy(array $criteria, array $orderBy = null)
 */
final class JobAuthorCurrentOptimizeRepository extends AbstractAnzuRepository
{
    public function existsWaitingForAuthor(string $authorId): bool
    {
        return null !== $this->findOneBy(['authorId' => $authorId, 'status' => JobStatus::Waiting]);
    }

    protected function getEntityClass(): string
    {
        return JobAuthorCurrentOptimize::class;
    }
}
