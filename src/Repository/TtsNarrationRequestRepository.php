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
