<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetSlot;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\AssetSlot;

class AssetSlotManager extends AbstractManager
{
    public function create(AssetSlot $assetSlot, bool $flush = true): AssetSlot
    {
        $this->trackCreation($assetSlot);
        $this->entityManager->persist($assetSlot);
        $this->flush($flush);

        return $assetSlot;
    }

    public function delete(AssetSlot $assetSlot, bool $flush = true): bool
    {
        $assetSlot->getAsset()->removeSlot($assetSlot);
        $assetSlot->getAssetFile()->getSlots()->removeElement($assetSlot);
        $this->entityManager->remove($assetSlot);
        $this->flush($flush);

        return true;
    }
}
