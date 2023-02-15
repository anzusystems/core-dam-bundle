<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event\Dispatcher;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\DamUser;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\RegionOfInterest;
use AnzuSystems\CoreDamBundle\Event\AssetFileDeleteEvent;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class AssetFileDeleteEventDispatcher
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
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

        $this->eventStack[] = new AssetFileDeleteEvent(
            deleteId: $deleteId,
            deleteAssetId: $deleteAssetId,
            assetFile: $assetFile,
            type: $type,
            deletedBy: $deletedBy,
            roiPositions: $this->getRoiIds($assetFile),
            extSystem: $assetFile->getExtSystem()->getSlug()
        );
    }

    public function dispatchFileDelete(
        string $deleteId,
        string $deleteAssetId,
        AssetFile $assetFile,
        AssetType $type,
        DamUser $deletedBy,
    ): void {
        $this->dispatcher->dispatch(
            new AssetFileDeleteEvent(
                deleteId: $deleteId,
                deleteAssetId: $deleteAssetId,
                assetFile: $assetFile,
                type: $type,
                deletedBy: $deletedBy,
                roiPositions: $this->getRoiIds($assetFile),
                extSystem: $assetFile->getExtSystem()->getSlug()
            )
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
