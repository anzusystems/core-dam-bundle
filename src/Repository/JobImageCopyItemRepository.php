<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\JobImageCopy;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopyItem;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCopyStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Order;

/**
 * @extends AbstractAnzuRepository<JobImageCopyItem>
 *
 * @method JobImageCopyItem|null find($id, $lockMode = null, $lockVersion = null)
 * @method JobImageCopyItem|null findOneBy(array $criteria, array $orderBy = null)
 */
final class JobImageCopyItemRepository extends AbstractAnzuRepository
{
    public function findUnassignedByJob(JobImageCopy $jobImageCopy, int $idFrom = 0, int $limit = 10): Collection
    {
        return new ArrayCollection(
            $this->createQueryBuilder('entity')
                ->andWhere('IDENTITY(entity.job) = :jobId')
                ->andWhere('entity.id > :idFrom')
                ->andWhere('entity.status = :status')
                ->setParameter('jobId', (int) $jobImageCopy->getId())
                ->setParameter('idFrom', $idFrom)
                ->setParameter('status', AssetFileCopyStatus::Unassigned->value)
                ->addOrderBy('entity.id', Order::Ascending->value)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult()
        );
    }

    protected function getEntityClass(): string
    {
        return JobImageCopyItem::class;
    }
}
