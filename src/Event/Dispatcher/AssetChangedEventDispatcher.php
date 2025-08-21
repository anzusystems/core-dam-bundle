<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event\Dispatcher;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Event\AssetChangedEvent;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final readonly class AssetChangedEventDispatcher
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * @param Collection<int, Asset> $affectedAssets
     */
    public function dispatchAssetChangedEvent(Collection $affectedAssets): void
    {
        $this->dispatcher->dispatch($this->createAssetMetadataBulkChangedEvent($affectedAssets));
    }

    /**
     * @param Collection<int, Asset> $affectedAssets
     */
    private function createAssetMetadataBulkChangedEvent(Collection $affectedAssets): AssetChangedEvent
    {
        return new AssetChangedEvent(
            affectedAssets: $affectedAssets,
        );
    }
}
