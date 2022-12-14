<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionProcessStatus;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query\Expr;

/**
 * @extends AbstractAnzuRepository<Distribution>
 *
 * @method Distribution|null find($id, $lockMode = null, $lockVersion = null)
 * @method Distribution|null findOneBy(array $criteria, array $orderBy = null)
 */
final class DistributionRepository extends AbstractAnzuRepository
{
    /**
     * @throws NonUniqueResultException
     */
    public function isNotBlockByNotFinished(Distribution $distribution): bool
    {
        return null === $this->createQueryBuilder('entity')
            ->innerJoin(
                join: 'entity.blockedBy',
                alias: 'blockedBy',
                conditionType: Expr\Join::WITH,
                condition: 'blockedBy.status in (:status)'
            )
            ->andWhere('entity.id = :distributionId')
            ->setParameter('status', DistributionProcessStatus::NOT_FINISHED_MAP)
            ->setParameter('distributionId', $distribution->getId())
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByAssetFileAndDistributionService(string $assetFileId, string $distributionService): ?Distribution
    {
        return $this->findOneBy([
            'assetFileId' => $assetFileId,
            'distributionService' => $distributionService,
        ]);
    }

    protected function getEntityClass(): string
    {
        return Distribution::class;
    }
}
