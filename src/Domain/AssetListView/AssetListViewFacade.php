<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetListView;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Entity\AssetListView;

final class AssetListViewFacade
{
    use ValidatorAwareTrait;

    public function __construct(
        private readonly AssetListViewManager $assetListViewManager,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function create(AssetListView $assetListView): AssetListView
    {
        $this->validator->validate($assetListView);

        return $this->assetListViewManager->create($assetListView);
    }

    /**
     * @throws ValidationException
     */
    public function update(AssetListView $assetListView, AssetListView $newAssetListView): AssetListView
    {
        $this->validator->validate($newAssetListView, $assetListView);

        return $this->assetListViewManager->update($assetListView, $newAssetListView);
    }

    public function delete(AssetListView $assetListView): void
    {
        $this->assetListViewManager->delete($assetListView);
    }
}
