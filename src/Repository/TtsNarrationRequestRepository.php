<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestMode;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestStatus;
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
            'stableAssetId' => $stableAssetId,
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
            'stableAssetId' => $stableAssetIds,
            'mode' => TtsRequestMode::Regenerate,
            'status' => TtsRequestStatus::CANCELLABLE_STATUSES,
        ]);

        $byStable = [];
        foreach ($requests as $request) {
            $stableId = $request->getStableAssetId();
            if (null !== $stableId) {
                $byStable[$stableId] = $request;
            }
        }

        return $byStable;
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
     * Latest request that touched the given asset — either as Initial result (resultAssetId)
     * or as Regenerate target (stableAssetId). Powers the "open source request" link in the
     * asset detail TTS panel.
     *
     * Implemented as two index-friendly lookups (one per column) instead of a single OR query,
     * which MySQL cannot satisfy with index_merge alongside ORDER BY createdAt — that would
     * degrade to a filesort as the table grows.
     */
    public function findLastIdByAsset(string $assetId): ?string
    {
        $candidates = [];
        foreach (['resultAssetId', 'stableAssetId'] as $field) {
            $row = $this->createQueryBuilder('r')
                ->select('r.id', 'r.createdAt')
                ->where(sprintf('r.%s = :id', $field))
                ->setParameter('id', $assetId)
                ->orderBy('r.createdAt', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult()
            ;
            if (null !== $row) {
                $candidates[] = $row;
            }
        }

        if ([] === $candidates) {
            return null;
        }

        usort($candidates, static fn (array $a, array $b): int => $b['createdAt'] <=> $a['createdAt']);

        return (string) $candidates[0]['id'];
    }

    protected function getEntityClass(): string
    {
        return TtsNarrationRequest::class;
    }
}
