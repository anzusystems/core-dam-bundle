<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFileRoute;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileStash;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;
use AnzuSystems\CoreDamBundle\Model\Enum\RouteMode;
use AnzuSystems\CoreDamBundle\Traits\FileHelperTrait;
use League\Flysystem\FilesystemException;

final class AssetFileRouteManager extends AbstractManager
{
    public function __construct(
        private readonly FileStash $stash
    ) {
    }

    public function create(AssetFileRoute $assetFileRoute, bool $flush = true): AssetFileRoute
    {
        $this->trackCreation($assetFileRoute);
        $this->entityManager->persist($assetFileRoute);
        $this->flush($flush);

        return $assetFileRoute;
    }

    public function delete(AssetFileRoute $assetFileRoute, bool $flush = true): bool
    {
        if ($assetFileRoute->getTargetAssetFile()->getMainRoute() === $assetFileRoute) {
            $assetFileRoute->getTargetAssetFile()->setMainRoute(null);
        }
        $assetFileRoute->getTargetAssetFile()->getRoutes()->removeElement($assetFileRoute);
        $this->entityManager->remove($assetFileRoute);
        $this->flush($flush);

        return true;
    }

    /**
     * @throws FilesystemException
     */
    public function clearRoutes(AssetFile $assetFile, bool $flush = true): bool
    {
        foreach ($assetFile->getRoutes() as $route) {
            if ($route->getMode()->is(RouteMode::StorageCopy)) {
                $this->stash->add($route);
            }
            $this->entityManager->remove($route);
        }

        if ($flush) {
            $this->flush($flush);
            $this->stash->emptyAll();
        }

        return true;
    }
}
