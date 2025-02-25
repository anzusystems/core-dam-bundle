<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\PublicExport;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
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
        $publicExport->setExtSystem($publicExport->getAssetLicence()->getExtSystem());
        $this->validator->validate($publicExport);
        $this->publicExportManager->create($publicExport);

        return $publicExport;
    }

    /**
     * @throws ValidationException
     */
    public function update(PublicExport $publicExport, PublicExport $newPublicExport): PublicExport
    {
        $publicExport->setExtSystem($newPublicExport->getAssetLicence()->getExtSystem());
        $this->validator->validate($newPublicExport, $publicExport);
        $this->publicExportManager->update($publicExport, $newPublicExport);

        return $publicExport;
    }

    public function delete(PublicExport $publicExport): bool
    {
        $this->publicExportManager->delete($publicExport);

        return true;
    }
}
