<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsAudioStatus;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
            ->addSelect('a')
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
     * Content-addressed dedup: an active asset with the same source text + voiceFamily already exists in this
     * licence. Identical text in the same voice produces identical audio, so the caller reuses it instead
     * of paying for another synthesis. Keyed on (licence, sourceTextHash, voiceFamily) — language rides on
     * the licence's ext-system, the voiceFamily pins the voice.
     *
     * @param non-empty-list<TtsAudioStatus> $activeStatuses
     */
    public function findActiveByContent(
        AssetLicence $licence,
        string $sourceTextHash,
        VoiceFamily $voiceFamily,
        array $activeStatuses = [TtsAudioStatus::Active, TtsAudioStatus::Superseding, TtsAudioStatus::Cancelling],
    ): ?TtsAsset {
        return $this->createQueryBuilder('ta')
            ->innerJoin('ta.asset', 'a')
            ->where('a.licence = :licence')
            ->andWhere('ta.sourceTextHash = :sourceTextHash')
            ->andWhere('ta.voiceFamily = :voiceFamily')
            ->andWhere('ta.status IN (:statuses)')
            ->setParameter('licence', $licence)
            ->setParameter('sourceTextHash', $sourceTextHash)
            ->setParameter('voiceFamily', $voiceFamily)
            ->setParameter('statuses', $activeStatuses)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * TtsAssets stuck in 'superseding' whose underlying Asset has not been touched since $threshold.
     * Used by the cleanup cron to cancel TTL-exceeded regen jobs.
     *
     * @return Collection<int, TtsAsset>
     */
    public function findStuckSuperseding(DateTimeImmutable $threshold, int $limit): Collection
    {
        return new ArrayCollection(
            $this->createQueryBuilder('ta')
                ->where('ta.status = :status')
                ->andWhere('ta.modifiedAt < :threshold')
                ->setParameter('status', TtsAudioStatus::Superseding)
                ->setParameter('threshold', $threshold)
                ->addOrderBy('ta.modifiedAt', 'ASC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult()
        );
    }

    public function existsByVoiceFamily(VoiceFamily $voiceFamily): bool
    {
        return (bool) $this->count(['voiceFamily' => $voiceFamily]);
    }

    protected function getEntityClass(): string
    {
        return TtsAsset::class;
    }
}
