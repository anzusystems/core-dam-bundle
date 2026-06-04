<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
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
     * The in-flight Regenerate request targeting the given stable Asset, if any. By invariant
     * there is at most one (concurrent regen on the same stable is blocked by TtsAssetLocker
     * via the TtsAsset state machine).
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
     * Batch variant of {@see findActiveRegenForStable} — one query for a list of stable Asset IDs.
     *
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
     * Initial requests left in Processing past the given threshold — a worker that claimed (Waiting →
     * Processing) then died before reaching a terminal status. The cleanup cron fails these so their
     * idempotency key frees up for a fresh dispatch. The threshold must exceed the worker time-limit.
     *
     * @return Collection<int, TtsNarrationRequest>
     */
    public function findStuckInitialProcessing(DateTimeImmutable $startedBefore, int $limit): Collection
    {
        return new ArrayCollection(
            $this->createQueryBuilder('r')
                ->where('r.mode = :mode')
                ->andWhere('r.status = :status')
                ->andWhere('r.startedAt < :startedBefore')
                ->setParameter('mode', TtsRequestMode::Initial)
                ->setParameter('status', TtsRequestStatus::Processing)
                ->setParameter('startedBefore', $startedBefore)
                ->addOrderBy('r.startedAt', 'ASC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult()
        );
    }

    /**
     * Requests left in Waiting past the threshold — the dispatch/plan message was lost (worker crash
     * before claim, at-most-once transport) so the request was never picked up. Fails them so the
     * idempotency key frees up. Uses modifiedAt since startedAt is only set at Processing.
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
     * Pessimistic-write lock for handler-side claim transitions (Waiting → Processing). Pub/Sub
     * redelivery (ack-deadline expiry, worker crash) can deliver the same message to a second
     * worker; both must serialise on this row so only one moves the request out of Waiting.
     */
    public function findForUpdate(string $id): ?TtsNarrationRequest
    {
        return $this->find($id, LockMode::PESSIMISTIC_WRITE);
    }

    /**
     * Latest request that touched the given asset (as Initial shell or Regenerate target). Powers the
     * "open source request" link in the asset detail TTS panel. Index-friendly: a single lookup on
     * {@see TtsNarrationRequest::$assetId} (IDX_tts_request_asset_mode_status leads with assetId).
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
