<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetLicenceGroup;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\AssetLicenceGroup;

final class AssetLicenceGroupManager extends AbstractManager
{
    public function create(AssetLicenceGroup $assetLicenceGroup, bool $flush = true): AssetLicenceGroup
    {
        $this->trackCreation($assetLicenceGroup);
        $this->entityManager->persist($assetLicenceGroup);
        $this->flush($flush);

        return $assetLicenceGroup;
    }

    public function update(AssetLicenceGroup $assetLicenceGroup, AssetLicenceGroup $newAssetLicenceGroup, bool $flush = true): AssetLicenceGroup
    {
        $this->trackModification($assetLicenceGroup);
        $assetLicenceGroup
            ->setName($newAssetLicenceGroup->getName())
        ;
        $this->colUpdate(
            oldCollection: $assetLicenceGroup->getLicences(),
            newCollection: $newAssetLicenceGroup->getLicences()
        );
        $this->flush($flush);

        return $assetLicenceGroup;
    }

    public function delete(AssetLicenceGroup $assetLicenceGroup, bool $flush = true): void
    {
        $this->entityManager->remove($assetLicenceGroup);
        $this->flush($flush);
    }
}
