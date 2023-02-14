<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event\Dispatcher;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\DamUser;
use AnzuSystems\CoreDamBundle\Event\AssetFileDeleteEvent;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class AssetFileDeleteEventDispatcher
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        /** @var list<AssetFileDeleteEvent> */
        private $eventStack = [],
    ) {
    }

    public function addEvent(
        string $deleteId,
        string $deleteAssetId,
        AssetFile $assetFile,
        AssetType $type,
        DamUser $deletedBy,
    ): void {
        $this->eventStack[] = new AssetFileDeleteEvent($deleteId, $deleteAssetId, $assetFile, $type, $deletedBy);
    }

    public function dispatchFileDelete(
        string $deleteId,
        string $deleteAssetId,
        AssetFile $assetFile,
        AssetType $type,
        DamUser $deletedBy,
    ): void {
        $this->dispatcher->dispatch(new AssetFileDeleteEvent($deleteId, $deleteAssetId, $assetFile, $type, $deletedBy));
    }

    public function dispatchAll(): void
    {
        foreach ($this->eventStack as $event) {
            $this->dispatcher->dispatch($event);
        }

        $this->eventStack = [];
    }
}
