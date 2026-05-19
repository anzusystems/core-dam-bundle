<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsAudioStatus;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;

/**
 * @extends AbstractAnzuRepository<TtsAsset>
 *
 * @method TtsAsset|null find($id, $lockMode = null, $lockVersion = null)
 * @method TtsAsset|null findOneBy(array $criteria, array $orderBy = null)
 */
final class TtsAssetRepository extends AbstractAnzuRepository
{
    public function findByAsset(Asset $asset): ?TtsAsset
    {
        return $this->findOneBy(['asset' => $asset]);
    }

    /**
     * Single JOIN-fetch of TtsAsset + its Asset by Asset id. Optional pessimistic write lock for
     * the regen / swap mutate paths — replaces the prior
     * `assetRepo->find(LOCK)` + `ttsAssetRepo->findByAsset()` two-query pattern.
     */
    public function findByAssetIdJoined(string $assetId, ?LockMode $lockMode = null): ?TtsAsset
    {
        $query = $this->createQueryBuilder('ta')
            ->innerJoin('ta.asset', 'a')
            ->where('a.id = :id')
            ->setParameter('id', $assetId)
            ->getQuery()
        ;
        if (null !== $lockMode) {
            $query->setLockMode($lockMode);
        }

        return $query->getOneOrNullResult();
    }

    /**
     * @param non-empty-list<TtsAudioStatus> $activeStatuses
     */
    public function findActiveByExt(
        string $extResourceName,
        string $extId,
        ExtSystem $extSystem,
        array $activeStatuses = [TtsAudioStatus::Active, TtsAudioStatus::Superseding, TtsAudioStatus::Cancelling],
    ): ?TtsAsset {
        return $this->createQueryBuilder('ta')
            ->innerJoin('ta.asset', 'a')
            ->where('a.extSystem = :extSystem')
            ->andWhere('ta.extResourceName = :extResourceName')
            ->andWhere('ta.extId = :extId')
            ->andWhere('ta.status IN (:statuses)')
            ->setParameter('extSystem', $extSystem)
            ->setParameter('extResourceName', $extResourceName)
            ->setParameter('extId', $extId)
            ->setParameter('statuses', $activeStatuses)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * TtsAssets stuck in 'superseding' whose underlying Asset has not been touched since $threshold.
     * Used by the cleanup cron to cancel TTL-exceeded regen jobs.
     *
     * @return list<TtsAsset>
     */
    public function findStuckSuperseding(DateTimeImmutable $threshold): array
    {
        return $this->createQueryBuilder('ta')
            ->where('ta.status = :status')
            ->andWhere('ta.modifiedAt < :threshold')
            ->setParameter('status', TtsAudioStatus::Superseding)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();
    }

    protected function getEntityClass(): string
    {
        return TtsAsset::class;
    }
}
