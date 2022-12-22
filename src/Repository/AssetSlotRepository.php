<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\AssetSlot;

/**
 * @extends AbstractAnzuRepository<AssetSlot>
 *
 * @method AssetSlot|null find($id, $lockMode = null, $lockVersion = null)
 * @method AssetSlot|null findOneBy(array $criteria, array $orderBy = null)
 */
final class AssetSlotRepository extends AbstractAnzuRepository
{
    public function findSlotByAssetAndTitle(string $assetId, string $title): ?AssetSlot
    {
        return $this->findOneBy([
            'asset' => $assetId,
            'name' => $title,
        ]);
    }

    protected function getEntityClass(): string
    {
        return AssetSlot::class;
    }
}
