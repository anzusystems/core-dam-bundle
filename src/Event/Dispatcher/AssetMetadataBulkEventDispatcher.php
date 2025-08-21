<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event\Dispatcher;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Event\AssetMetadataBulkChangedEvent;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final readonly class AssetMetadataBulkEventDispatcher
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * @param Collection<int, Asset> $affectedAssets
     */
    public function dispatchAssetMetadataBulkChanged(Collection $affectedAssets): void
    {
        $this->dispatcher->dispatch($this->createAssetMetadataBulkChangedEvent($affectedAssets));
    }

    /**
     * @param Collection<int, Asset> $affectedAssets
     */
    private function createAssetMetadataBulkChangedEvent(Collection $affectedAssets): AssetMetadataBulkChangedEvent
    {
        return new AssetMetadataBulkChangedEvent(
            affectedAssets: $affectedAssets,
        );
    }
}
