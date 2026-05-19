<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestMode;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestStatus;

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

    protected function getEntityClass(): string
    {
        return TtsNarrationRequest::class;
    }
}
