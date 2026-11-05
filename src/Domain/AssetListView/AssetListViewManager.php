<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetListView;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\AssetLicenceGroup;
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
     * Drops the licences from every targeted view that no longer reaches them through any of its other groups,
     * and nulls the view's upload licence when it no longer belongs to the view's remaining licences.
     * Excluding the edited group makes the result independent of whether its own change is already flushed.
     * Only the views listing these licences are loaded.
     *
     * @param list<int> $licenceIds
     */
    public function removeUnreachableLicences(array $licenceIds, AssetLicenceGroup $excludedGroup, bool $flush = true): void
    {
        $views = $this->assetListViewRepository->findTargetedByLicences($licenceIds);
        if ([] === $views) {
            return;
        }

        $reachedByView = $this->assetListViewRepository->findLicenceIdsReachedByOtherGroups(
            viewIds: array_map(static fn (AssetListView $view): int => (int) $view->getId(), $views),
            licenceIds: $licenceIds,
            excludedGroupId: (int) $excludedGroup->getId(),
        );
        $checked = array_flip($licenceIds);

        foreach ($views as $view) {
            $reached = array_flip($reachedByView[(int) $view->getId()] ?? []);
            $modified = false;
            foreach ($view->getLicences()->toArray() as $licence) {
                $licenceId = (int) $licence->getId();
                if (isset($checked[$licenceId]) && false === isset($reached[$licenceId])) {
                    $view->getLicences()->removeElement($licence);
                    $modified = true;
                }
            }

            $uploadLicence = $view->getUploadLicence();
            if (
                $uploadLicence instanceof AssetLicence
                && isset($checked[(int) $uploadLicence->getId()])
                && false === $view->getLicences()->contains($uploadLicence)
            ) {
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
