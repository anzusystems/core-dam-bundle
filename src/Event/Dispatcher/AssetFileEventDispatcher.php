<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event\Dispatcher;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Event\AssetFileChangeStateEvent;
use AnzuSystems\CoreDamBundle\Event\AssetFileDuplicatePreFlushEvent;
use AnzuSystems\CoreDamBundle\Event\MetadataProcessedEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class AssetFileEventDispatcher
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    public function dispatchAssetFileChanged(AssetFile $assetFile): void
    {
        $this->dispatcher->dispatch(new AssetFileChangeStateEvent($assetFile));
    }

    public function dispatchMetadataProcessed(AssetFile $assetFile): void
    {
        $this->dispatcher->dispatch(new MetadataProcessedEvent($assetFile));
    }

    public function dispatchDuplicatePreFlush(AssetFile $assetFile, AssetFile $originAssetFile): void
    {
        $this->dispatcher->dispatch(new AssetFileDuplicatePreFlushEvent($assetFile, $originAssetFile));
    }
}
