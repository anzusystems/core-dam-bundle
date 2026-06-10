<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\PublicExport;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\PublicExport;

final class PublicExportFacade
{
    use ValidatorAwareTrait;

    public function __construct(
        private readonly PublicExportManager $publicExportManager,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function create(PublicExport $publicExport): PublicExport
    {
        $this->reconcileLicences($publicExport);
        $this->validator->validate($publicExport);
        $this->publicExportManager->create($publicExport);

        return $publicExport;
    }

    /**
     * @throws ValidationException
     */
    public function update(PublicExport $publicExport, PublicExport $newPublicExport): PublicExport
    {
        $this->reconcileLicences($newPublicExport);
        $publicExport->setExtSystem($newPublicExport->getExtSystem());
        $this->validator->validate($newPublicExport, $publicExport);
        $this->publicExportManager->update($publicExport, $newPublicExport);

        return $publicExport;
    }

    public function delete(PublicExport $publicExport): bool
    {
        $this->publicExportManager->delete($publicExport);

        return true;
    }

    /**
     * Back-compat: seed licences collection from legacy single licence; sync deprecated fields to primary.
     */
    private function reconcileLicences(PublicExport $publicExport): void
    {
        $legacy = $publicExport->getAssetLicence();
        if ($publicExport->getLicences()->isEmpty() && $legacy instanceof AssetLicence) {
            $publicExport->addLicence($legacy);
        }

        $primary = $publicExport->getLicences()->first();
        if ($primary instanceof AssetLicence) {
            $publicExport->setAssetLicence($primary);
            $publicExport->setExtSystem($primary->getExtSystem());
        }
    }
}
