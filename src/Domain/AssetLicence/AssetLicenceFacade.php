<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetLicence;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Validator\EntityValidator;

final class AssetLicenceFacade
{
    public function __construct(
        private readonly EntityValidator $entityValidator,
        private readonly AssetLicenceManager $assetLicenceManager,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function create(AssetLicence $assetLicence): AssetLicence
    {
        $this->entityValidator->validate($assetLicence);

        return $this->assetLicenceManager->create($assetLicence);
    }

    /**
     * @throws ValidationException
     */
    public function update(AssetLicence $assetLicence, AssetLicence $newAssetLicence): AssetLicence
    {
        $this->entityValidator->validate($newAssetLicence, $assetLicence);

        return $this->assetLicenceManager->update($assetLicence, $newAssetLicence);
    }
}
