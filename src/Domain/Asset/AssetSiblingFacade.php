<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Asset;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;

final readonly class AssetSiblingFacade
{
    public function __construct(
        private AssetManager $assetManager,
    ) {
    }

    /**
     * @throws ForbiddenOperationException
     */
    public function updateSibling(Asset $asset, ?Asset $targetAsset = null): Asset
    {
        $this->validateSibling($asset, $targetAsset);

        $asset = null === $targetAsset
            ? $this->removeSibling($asset)
            : $this->setSibling($asset, $targetAsset)
        ;

        $this->assetManager->updateExisting($asset);

        return $asset;
    }

    private function setSibling(Asset $asset, Asset $targetAsset): Asset
    {
        $previousTargetAssetSibling = $targetAsset->getSiblingToAsset();
        if ($previousTargetAssetSibling instanceof Asset) {
            $previousTargetAssetSibling->setSiblingToAsset(null);
        }
        $targetAsset->setSiblingToAsset($asset);

        $previousAssetSibling = $asset->getSiblingToAsset();
        if ($previousAssetSibling instanceof Asset) {
            $previousAssetSibling->setSiblingToAsset(null);
        }
        $asset->setSiblingToAsset($targetAsset);

        return $asset;
    }

    private function removeSibling(Asset $asset): Asset
    {
        $previousAssetSibling = $asset->getSiblingToAsset();
        if ($previousAssetSibling instanceof Asset) {
            $previousAssetSibling->setSiblingToAsset(null);
        }
        $asset->setSiblingToAsset(null);

        return $asset;
    }

    private function validateSibling(Asset $asset, ?Asset $targetAsset = null): void
    {
        if (null === $targetAsset) {
            return;
        }
        if (false === $asset->getAttributes()->getAssetType()->isAllowedSiblingType(
            $targetAsset->getAttributes()->getAssetType()
        )
        ) {
            throw new ForbiddenOperationException(ForbiddenOperationException::DETAIL_INVALID_ASSET_TYPE);
        }

        if ($asset->getLicence()->isNot($targetAsset->getLicence())) {
            throw new ForbiddenOperationException(ForbiddenOperationException::LICENCE_MISMATCH);
        }
    }
}
