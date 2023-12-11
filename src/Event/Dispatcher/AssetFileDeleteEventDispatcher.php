<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event\Dispatcher;

use AnzuSystems\CoreDamBundle\Cache\AssetFileRouteGenerator;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;
use AnzuSystems\CoreDamBundle\Entity\DamUser;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\RegionOfInterest;
use AnzuSystems\CoreDamBundle\Event\AssetFileDeleteEvent;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class AssetFileDeleteEventDispatcher
{
    public function __construct(
        private readonly AssetFileRouteGenerator $assetFileRouteGenerator,
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
        $assetFile->getAsset()->getExtSystem()->getSlug();

        $this->eventStack[] = $this->createAssetFileDeleteEvent(
            $deleteId,
            $deleteAssetId,
            $assetFile,
            $type,
            $deletedBy
        );
    }

    public function dispatchFileDelete(
        string $deleteId,
        string $deleteAssetId,
        AssetFile $assetFile,
        AssetType $type,
        DamUser $deletedBy,
    ): void {
        $this->dispatcher->dispatch($this->createAssetFileDeleteEvent(
            $deleteId,
            $deleteAssetId,
            $assetFile,
            $type,
            $deletedBy
        ));
    }

    public function createAssetFileDeleteEvent(
        string $deleteId,
        string $deleteAssetId,
        AssetFile $assetFile,
        AssetType $type,
        DamUser $deletedBy,
    ): AssetFileDeleteEvent {
        return new AssetFileDeleteEvent(
            deleteId: $deleteId,
            deleteAssetId: $deleteAssetId,
            assetFile: $assetFile,
            type: $type,
            deletedBy: $deletedBy,
            roiPositions: $this->getRoiIds($assetFile),
            extSystem: $assetFile->getExtSystem()->getSlug(),
            routePaths: $assetFile->getRoutes()->map(
                fn (AssetFileRoute $route): string => $this->assetFileRouteGenerator->getFullUrl($route)
            )->toArray()
        );
    }

    public function dispatchAll(): void
    {
        foreach ($this->eventStack as $event) {
            $this->dispatcher->dispatch($event);
        }

        $this->eventStack = [];
    }

    private function getRoiIds(AssetFile $assetFile): array
    {
        if ($assetFile instanceof ImageFile) {
            return $assetFile->getRegionsOfInterest()->map(
                fn (RegionOfInterest $regionOfInterest): int => $regionOfInterest->getPosition()
            )->toArray();
        }

        return [];
    }
}
