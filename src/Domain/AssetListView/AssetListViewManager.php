<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetListView;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\AssetListView;

final class AssetListViewManager extends AbstractManager
{
    public function create(AssetListView $assetListView, bool $flush = true): AssetListView
    {
        $this->trackCreation($assetListView);
        $this->entityManager->persist($assetListView);
        $this->flush($flush);

        return $assetListView;
    }

    public function update(AssetListView $assetListView, AssetListView $newAssetListView, bool $flush = true): AssetListView
    {
        $this->trackModification($assetListView);
        $assetListView
            ->setName($newAssetListView->getName())
            ->setExtSystem($newAssetListView->getExtSystem())
            ->setPosition($newAssetListView->getPosition())
            ->setUploadLicence($newAssetListView->getUploadLicence())
        ;
        $this->colUpdate($assetListView->getGroups(), $newAssetListView->getGroups());
        $this->colUpdate($assetListView->getLicences(), $newAssetListView->getLicences());
        $this->flush($flush);

        return $assetListView;
    }

    public function delete(AssetListView $assetListView, bool $flush = true): void
    {
        $this->entityManager->remove($assetListView);
        $this->flush($flush);
    }
}
