<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetLicenceGroup;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Helper\CollectionHelper;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Domain\AssetListView\AssetListViewManager;
use AnzuSystems\CoreDamBundle\Entity\AssetLicenceGroup;

final class AssetLicenceGroupFacade
{
    use ValidatorAwareTrait;

    public function __construct(
        private readonly AssetLicenceGroupManager $assetLicenceGroupManager,
        private readonly AssetListViewManager $assetListViewManager,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function create(AssetLicenceGroup $assetLicenceGroup): AssetLicenceGroup
    {
        $this->validator->validate($assetLicenceGroup);

        return $this->assetLicenceGroupManager->create($assetLicenceGroup);
    }

    /**
     * @throws ValidationException
     */
    public function update(AssetLicenceGroup $assetLicenceGroup, AssetLicenceGroup $newAssetLicenceGroup): AssetLicenceGroup
    {
        $this->validator->validate($newAssetLicenceGroup, $assetLicenceGroup);

        $removedLicenceIds = CollectionHelper::traversableToIds(
            CollectionHelper::colDiff($assetLicenceGroup->getLicences(), $newAssetLicenceGroup->getLicences())
        );

        $this->assetLicenceGroupManager->update($assetLicenceGroup, $newAssetLicenceGroup, flush: false);
        // A view can end up without licences here. That is an administrator's mistake, not a broken
        // state: the resolver stops offering the view until a licence is put back.
        $this->assetListViewManager->removeUnreachableLicences($removedLicenceIds, flush: false);
        $this->assetLicenceGroupManager->flush();

        return $assetLicenceGroup;
    }
}
