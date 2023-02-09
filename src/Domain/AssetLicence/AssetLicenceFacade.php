<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetLicence;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;

final class AssetLicenceFacade
{
    use ValidatorAwareTrait;

    public function __construct(
        private readonly AssetLicenceManager $assetLicenceManager,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function create(AssetLicence $assetLicence): AssetLicence
    {
        $this->validator->validate($assetLicence);

        return $this->assetLicenceManager->create($assetLicence);
    }

    /**
     * @throws ValidationException
     */
    public function update(AssetLicence $assetLicence, AssetLicence $newAssetLicence): AssetLicence
    {
        $this->validator->validate($newAssetLicence, $assetLicence);

        return $this->assetLicenceManager->update($assetLicence, $newAssetLicence);
    }
}
