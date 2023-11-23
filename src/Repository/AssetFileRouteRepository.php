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
    public function findByAssetId(string $assetId): ?AssetFileRoute
    {
        return $this->findOneBy([
            'assetFileId' => $assetId
        ]);
    }

    protected function getEntityClass(): string
    {
        return AssetFileRoute::class;
    }
}
