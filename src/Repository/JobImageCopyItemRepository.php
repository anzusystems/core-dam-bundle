<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\JobImageCopy;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopyItem;
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
    public function findByJob(JobImageCopy $jobImageCopy, int $idFrom = 0, int $limit = 10): Collection
    {
        // todo status
        return new ArrayCollection(
            $this->createQueryBuilder('entity')
                ->andWhere('IDENTITY(entity.job) = :jobId')
                ->andWhere('entity.id > :idFrom')
                ->setParameter('jobId', (int) $jobImageCopy->getId())
                ->setParameter('idFrom', $idFrom)
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
