<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetLicenceGroup;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Entity\AssetLicenceGroup;

final class AssetLicenceGroupFacade
{
    use ValidatorAwareTrait;

    public function __construct(
        private readonly AssetLicenceGroupManager $assetLicenceGroupManager,
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

        return $this->assetLicenceGroupManager->update($assetLicenceGroup, $newAssetLicenceGroup);
    }
}
