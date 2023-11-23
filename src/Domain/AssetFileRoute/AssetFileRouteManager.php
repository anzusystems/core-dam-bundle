<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFileRoute;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;

final class AssetFileRouteManager extends AbstractManager
{
    public function create(AssetFileRoute $assetFileRoute, bool $flush = true): AssetFileRoute
    {
        $this->trackCreation($assetFileRoute);
        $this->entityManager->persist($assetFileRoute);
        $this->flush($flush);

        return $assetFileRoute;
    }

    public function delete(AssetFileRoute $assetFileRoute, bool $flush = true): bool
    {
        $this->entityManager->remove($assetFileRoute);
        $this->flush($flush);

        return true;
    }
}
