<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetLicenceGroup;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\AssetLicenceGroup;
use Doctrine\Common\Collections\Collection;

final class AssetLicenceGroupManager extends AbstractManager
{
    public function create(AssetLicenceGroup $assetLicenceGroup, bool $flush = true): AssetLicenceGroup
    {
        $this->trackCreation($assetLicenceGroup);
        $this->entityManager->persist($assetLicenceGroup);
        foreach ($assetLicenceGroup->getLicences() as $licence) {
            $licence->getGroups()->add($assetLicenceGroup);
        }
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
            newCollection: $newAssetLicenceGroup->getLicences(),
            addElementFn: function (Collection $oldCollection, AssetLicence $licence) use ($assetLicenceGroup): bool {
                $licence->getGroups()->add($assetLicenceGroup);
                $oldCollection->add($licence);

                return true;
            },
            removeElementFn: function (Collection $oldCollection, AssetLicence $licence) use ($assetLicenceGroup): bool {
                $licence->getGroups()->removeElement($assetLicenceGroup);
                $oldCollection->removeElement($licence);

                return true;
            }
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
