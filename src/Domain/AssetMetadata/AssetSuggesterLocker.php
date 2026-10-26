<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetMetadata;

use AnzuSystems\CommonBundle\Util\ResourceLocker;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AssetSuggesterLocker
{
    private const string LOCK_PREFIX = 'lock_suggester_';
    // Spans refresh, suggestions, autocomplete and flush only. A killed holder must release it well before
    // the 30 s admin request timeout.
    private const float TTL = 5.0;

    public function __construct(
        private ResourceLocker $resourceLocker,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function lock(Asset $asset): void
    {
        $this->resourceLocker->lock(self::getLockName($asset), ttl: self::TTL);
        $this->refresh($asset);
    }

    public function unLock(Asset $asset): void
    {
        $this->resourceLocker->unLock(self::getLockName($asset));
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
}
