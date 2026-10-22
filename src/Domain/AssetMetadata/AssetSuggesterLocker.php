<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetMetadata;

use AnzuSystems\CommonBundle\Util\ResourceLocker;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AssetSuggesterLocker
{
    private const string LOCK_PREFIX = 'lock_suggester_';

    public function __construct(
        private ResourceLocker $resourceLocker,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function lock(Asset $asset): void
    {
        $this->resourceLocker->lock(self::getLockName($asset));
        $this->refresh($asset);
    }

    public function unLock(Asset $asset): void
    {
        $this->resourceLocker->unLock(self::getLockName($asset));
    }

    /**
     * @param iterable<Asset> $assets
     */
    public function lockAll(iterable $assets): void
    {
        foreach ($this->sortedUnique($assets) as $asset) {
            $this->lock($asset);
        }
    }

    /**
     * @param iterable<Asset> $assets
     */
    public function unLockAll(iterable $assets): void
    {
        foreach ($this->sortedUnique($assets) as $asset) {
            $this->unLock($asset);
        }
    }

    private static function getLockName(Asset $asset): string
    {
        return self::LOCK_PREFIX . (string) $asset->getId();
    }

    private function refresh(Asset $asset): void
    {
        $this->entityManager->refresh($asset);
        $this->entityManager->refresh($asset->getMetadata());
    }

    /**
     * Ascending by id, so two bulk requests lock the same assets in the same order.
     *
     * @param iterable<Asset> $assets
     *
     * @return array<string, Asset>
     */
    private function sortedUnique(iterable $assets): array
    {
        $sorted = [];
        foreach ($assets as $asset) {
            $sorted[(string) $asset->getId()] = $asset;
        }
        ksort($sorted);

        return $sorted;
    }
}
