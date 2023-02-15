<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event\Dispatcher;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\DamUser;
use AnzuSystems\CoreDamBundle\Event\AssetDeleteEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class AssetEventDispatcher
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        /** @var list<AssetDeleteEvent> */
        private $eventStack = [],
    ) {
    }

    public function addEvent(string $deleteId, Asset $asset, DamUser $deletedBy): void
    {
        $this->eventStack[] = new AssetDeleteEvent($deleteId, $asset, $deletedBy);
    }

    public function dispatchAssetDelete(string $deleteId, Asset $asset, DamUser $deletedBy): void
    {
        $this->dispatcher->dispatch(new AssetDeleteEvent($deleteId, $asset, $deletedBy));
    }

    public function dispatchAll(): void
    {
        foreach ($this->eventStack as $event) {
            $this->dispatcher->dispatch($event);
        }

        $this->eventStack = [];
    }
}
