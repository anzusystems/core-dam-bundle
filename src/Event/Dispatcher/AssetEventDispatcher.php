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
    ) {
    }

    public function dispatchAssetDelete(string $deleteId, Asset $asset, DamUser $deletedBy): void
    {
        $this->dispatcher->dispatch(new AssetDeleteEvent($deleteId, $asset, $deletedBy));
    }
}
