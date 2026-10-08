<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetListView;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\AssetLicenceGroup;
use AnzuSystems\CoreDamBundle\Entity\AssetListView;
use AnzuSystems\CoreDamBundle\Repository\DBALRepository\AssetListViewDBALRepository;
use Doctrine\DBAL\Exception;

final class AssetListViewManager extends AbstractManager
{
    public function __construct(
        private readonly AssetListViewDBALRepository $assetListViewDBALRepository,
    ) {
    }

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

    /**
     * Drops the licences from every targeted view that no longer reaches them through any of its other groups,
     * and nulls the view's upload licence when it no longer belongs to the view's remaining licences.
     * Excluding the edited group makes the result independent of whether its own change is already flushed.
     *
     * @param list<int> $licenceIds
     *
     * @throws Exception
     */
    public function removeUnreachableLicences(array $licenceIds, AssetLicenceGroup $excludedGroup): void
    {
        if ([] === $licenceIds) {
            return;
        }

        $excludedGroupId = (int) $excludedGroup->getId();
        $this->assetListViewDBALRepository->deleteLicencesUnreachableByOtherGroups($licenceIds, $excludedGroupId);
        $this->assetListViewDBALRepository->clearUploadLicenceNotInView($licenceIds);
    }
}
