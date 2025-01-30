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

        return null === $targetAsset
            ? $this->assetManager->removeSibling($asset)
            : $this->assetManager->setSibling($asset, $targetAsset)
        ;
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
