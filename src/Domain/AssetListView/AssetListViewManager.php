<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetListView;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\AssetListView;
use AnzuSystems\CoreDamBundle\Repository\AssetListViewRepository;

final class AssetListViewManager extends AbstractManager
{
    public function __construct(
        private readonly AssetListViewRepository $assetListViewRepository,
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
     * Drops the licences that its groups no longer reach from every targeted view listing one of the licences,
     * and nulls the view's upload licence when it no longer belongs to the view's remaining licences.
     * Call it after the group update: the edited group comes from the identity map with its unflushed licences.
     *
     * @param list<int> $licenceIds
     */
    public function removeUnreachableLicences(array $licenceIds, bool $flush = true): void
    {
        foreach ($this->assetListViewRepository->findTargetedByLicences($licenceIds) as $view) {
            $reachable = $view->getReachableLicences();
            $modified = false;
            foreach ($view->getLicences()->toArray() as $licence) {
                if (false === isset($reachable[(int) $licence->getId()])) {
                    $view->getLicences()->removeElement($licence);
                    $modified = true;
                }
            }

            $uploadLicence = $view->getUploadLicence();
            if ($uploadLicence instanceof AssetLicence && false === $view->getLicences()->contains($uploadLicence)) {
                $view->setUploadLicence(null);
                $modified = true;
            }

            if ($modified) {
                $this->trackModification($view);
            }
        }
        $this->flush($flush);
    }

    /**
     * The upload licence FK is RESTRICT, so a licence about to be deleted first stops being any view's upload licence.
     *
     * @param list<int> $licenceIds
     */
    public function unsetUploadLicences(array $licenceIds, bool $flush = true): void
    {
        foreach ($this->assetListViewRepository->findByUploadLicenceIds($licenceIds) as $view) {
            $view->setUploadLicence(null);
            $this->trackModification($view);
        }
        $this->flush($flush);
    }
}
