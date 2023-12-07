<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;

/**
 * @extends AbstractAnzuRepository<AssetFileRoute>
 *
 * @method AssetFileRoute|null find($id, $lockMode = null, $lockVersion = null)
 * @method AssetFileRoute|null findOneBy(array $criteria, array $orderBy = null)
 */
final class AssetFileRouteRepository extends AbstractAnzuRepository
{
    public function findOneByUriPath(string $uri): ?AssetFileRoute
    {
        return $this->findOneBy([
            'uri.path' => $uri,
            //            'mode' => $uri
        ]);
    }

    public function findMainByAssetFile(string $assetId): ?AssetFileRoute
    {
        return $this->findOneBy([
            'targetAssetFile' => $assetId,
            'uri.main' => true,
        ]);
    }

    public function findOneMain(string $uri): ?AssetFileRoute
    {
        return $this->findOneBy([
            'uri.path' => $uri,
            'uri.main' => true,
        ]);
    }

    public function findByAssetId(string $assetId): ?AssetFileRoute
    {
        return $this->findOneBy([
            'assetFileId' => $assetId,
        ]);
    }

    protected function getEntityClass(): string
    {
        return AssetFileRoute::class;
    }
}
