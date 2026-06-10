<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle;

use AnzuSystems\CommonBundle\Util\ResourceLocker;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Serializes TTS lifecycle transitions via {@see ResourceLocker} (Redis). One lock per concern:
 *  - dispatch lock — concurrent initial dispatches of the same content (idempotency key),
 *  - request lock — every terminal transition of one request (worker finalize vs cancel vs failer),
 *  - asset lock — regen swap vs two-phase cancel vs failer release on one stable asset.
 *
 * withRequestLock() refreshes the entity after acquiring: ResourceLocker auto-refreshes only on a
 * contended acquire, but the conflicting writer may have acquired and released long before — every
 * decision must be made on row state, never on an identity-map snapshot.
 *
 * Lock ordering where both are held: asset → request (see AssetSwap::promote()), never the reverse.
 */
final readonly class TtsLocker
{
    private const string DISPATCH_LOCK_PREFIX = 'tts_dispatch_';
    private const string REQUEST_LOCK_PREFIX = 'tts_request_';
    private const string ASSET_LOCK_PREFIX = 'tts_asset_';

    public function __construct(
        private ResourceLocker $resourceLocker,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @template T
     *
     * @param callable(): T $fn
     *
     * @return T
     */
    public function withDispatchLock(string $idempotencyKey, callable $fn): mixed
    {
        return $this->withLock(self::DISPATCH_LOCK_PREFIX . $idempotencyKey, $fn);
    }

    /**
     * @template T
     *
     * @param callable(): T $fn
     *
     * @return T
     */
    public function withRequestLock(TtsNarrationRequest $request, callable $fn): mixed
    {
        return $this->withLock(
            self::REQUEST_LOCK_PREFIX . ((string) $request->getId()),
            function () use ($request, $fn): mixed {
                $this->entityManager->refresh($request);

                return $fn();
            },
        );
    }

    /**
     * @template T
     *
     * @param callable(): T $fn
     *
     * @return T
     */
    public function withAssetLock(string $assetId, callable $fn): mixed
    {
        return $this->withLock(self::ASSET_LOCK_PREFIX . $assetId, $fn);
    }

    /**
     * @template T
     *
     * @param callable(): T $fn
     *
     * @return T
     */
    private function withLock(string $lockName, callable $fn): mixed
    {
        $this->resourceLocker->lock($lockName);

        try {
            return $fn();
        } finally {
            $this->resourceLocker->unLock($lockName);
        }
    }
}
