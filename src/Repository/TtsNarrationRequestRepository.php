<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Entity\TtsSynthesisChunk;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestMode;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestStatus;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\LockMode;

/**
 * @extends AbstractAnzuRepository<TtsNarrationRequest>
 *
 * @method TtsNarrationRequest|null find($id, $lockMode = null, $lockVersion = null)
 * @method TtsNarrationRequest|null findOneBy(array $criteria, array $orderBy = null)
 */
final class TtsNarrationRequestRepository extends AbstractAnzuRepository
{
    /**
     * In-flight initial request holding the unique idempotency slot (the key is cleared on terminal).
     */
    public function findInFlightByIdempotencyKey(string $idempotencyKey): ?TtsNarrationRequest
    {
        return $this->findOneBy(['initialIdempotencyKey' => $idempotencyKey]);
    }

    /**
     * At most one in-flight Regenerate per stable Asset (enforced by TtsAssetLocker).
     */
    public function findActiveRegenForStable(string $stableAssetId): ?TtsNarrationRequest
    {
        return $this->findOneBy([
            'assetId' => $stableAssetId,
            'mode' => TtsRequestMode::Regenerate,
            'status' => TtsRequestStatus::CANCELLABLE_STATUSES,
        ]);
    }

    /**
     * @param list<string> $stableAssetIds
     *
     * @return array<string, TtsNarrationRequest> keyed by stableAssetId
     */
    public function findActiveRegensForStables(array $stableAssetIds): array
    {
        if ([] === $stableAssetIds) {
            return [];
        }

        $requests = $this->findBy([
            'assetId' => $stableAssetIds,
            'mode' => TtsRequestMode::Regenerate,
            'status' => TtsRequestStatus::CANCELLABLE_STATUSES,
        ]);

        $byStable = [];
        foreach ($requests as $request) {
            $assetId = $request->getAssetId();
            if (null !== $assetId) {
                $byStable[$assetId] = $request;
            }
        }

        return $byStable;
    }

    /**
     * Waiting requests past threshold — dispatch message lost; uses modifiedAt (startedAt is set only at Processing).
     *
     * @return Collection<int, TtsNarrationRequest>
     */
    public function findStuckWaiting(DateTimeImmutable $modifiedBefore, int $limit): Collection
    {
        return new ArrayCollection(
            $this->createQueryBuilder('r')
                ->where('r.status = :status')
                ->andWhere('r.modifiedAt < :modifiedBefore')
                ->setParameter('status', TtsRequestStatus::Waiting)
                ->setParameter('modifiedBefore', $modifiedBefore)
                ->addOrderBy('r.modifiedAt', 'ASC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult()
        );
    }

    /**
     * Processing requests with no chunk activity since $since — NOT EXISTS is trivially true on inline (no chunks),
     * so startedAt gates that path too.
     *
     * @return Collection<int, TtsNarrationRequest>
     */
    public function findStalledProcessing(DateTimeImmutable $since, int $limit): Collection
    {
        $recentChunk = $this->getEntityManager()->createQueryBuilder()
            ->select('1')
            ->from(TtsSynthesisChunk::class, 'activeChunk')
            ->where('activeChunk.request = r')
            ->andWhere('activeChunk.modifiedAt >= :since')
            ->getDQL()
        ;

        return new ArrayCollection(
            $this->createQueryBuilder('r')
                ->where('r.status = :status')
                ->andWhere('r.startedAt < :since')
                ->andWhere(sprintf('NOT EXISTS (%s)', $recentChunk))
                ->setParameter('status', TtsRequestStatus::Processing)
                ->setParameter('since', $since)
                ->addOrderBy('r.modifiedAt', 'ASC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult()
        );
    }

    /**
     * Pessimistic-write lock for Waiting → Processing claim; serialises Pub/Sub redelivery races.
     */
    public function findForUpdate(string $id): ?TtsNarrationRequest
    {
        return $this->find($id, LockMode::PESSIMISTIC_WRITE);
    }

    /**
     * Latest request id for an asset — powers the "open source request" link in the TTS panel.
     */
    public function findLastIdByAsset(string $assetId): ?string
    {
        $row = $this->createQueryBuilder('r')
            ->select('r.id')
            ->where('r.assetId = :id')
            ->setParameter('id', $assetId)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return null !== $row ? (string) $row['id'] : null;
    }

    protected function getEntityClass(): string
    {
        return TtsNarrationRequest::class;
    }
}
