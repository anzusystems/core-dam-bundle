<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetLicence;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;

final class AssetLicenceManager extends AbstractManager
{
    public function create(AssetLicence $assetLicence, bool $flush = true): AssetLicence
    {
        $this->trackCreation($assetLicence);
        if (empty($assetLicence->getName())) {
            $assetLicence->setName($assetLicence->getDefaultName());
        }
        $this->entityManager->persist($assetLicence);
        $this->flush($flush);

        return $assetLicence;
    }

    public function update(AssetLicence $assetLicence, AssetLicence $newAssetLicence, bool $flush = true): AssetLicence
    {
        $this->trackModification($assetLicence);
        $assetLicence
            ->setName($newAssetLicence->getName())
            ->setExtId($newAssetLicence->getExtId())
            ->setExtSystem($newAssetLicence->getExtSystem())
        ;
        if (empty($assetLicence->getName())) {
            $assetLicence->setName($assetLicence->getDefaultName());
        }
        $this->flush($flush);

        return $assetLicence;
    }
}
