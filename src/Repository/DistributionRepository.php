<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\ApiFilter\ApiResponseList;
use AnzuSystems\CoreDamBundle\ApiFilter\DistributionApiParams;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\CustomDistributionFilter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query\Expr;

/**
 * @extends AbstractAnzuRepository<Distribution>
 *
 * @method Distribution|null find($id, $lockMode = null, $lockVersion = null)
 * @method Distribution|null findOneBy(array $criteria, array $orderBy = null)
 */
class DistributionRepository extends AbstractAnzuRepository
{
    /**
     * @throws ORMException
     */
    public function findByApiParamsByAssetFile(ApiParams $apiParams, AssetFile $assetFile): ApiResponseList
    {
        return $this->findByApiParams(
            apiParams: DistributionApiParams::applyAssetFileCustomFilter($apiParams, $assetFile),
            customFilters: [new CustomDistributionFilter()]
        );
    }

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

    public function findByAssetFile(string $assetFileId): Collection
    {
        return new ArrayCollection(
            $this->findBy([
                'assetFileId' => $assetFileId,
            ])
        );
    }

    public function findByAsset(string $assetId): Collection
    {
        return new ArrayCollection(
            $this->findBy([
                'assetId' => $assetId,
            ])
        );
    }

    protected function getEntityClass(): string
    {
        return Distribution::class;
    }
}
