<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\PublicExport;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\PublicExport;

class PublicExportManager extends AbstractManager
{
    public function create(PublicExport $publicExport, bool $flush = true): PublicExport
    {
        $this->trackCreation($publicExport);
        $this->entityManager->persist($publicExport);
        $this->flush($flush);

        return $publicExport;
    }

    public function update(PublicExport $publicExport, PublicExport $newPublicExport, bool $flush = true): PublicExport
    {
        $this->trackModification($publicExport);

        $publicExport
            ->setType($newPublicExport->getType())
            ->setSlug($newPublicExport->getSlug())
            ->setAssetLicence($newPublicExport->getAssetLicence())
            ->setExtSystem($newPublicExport->getAssetLicence()->getExtSystem())
        ;

        $this->flush($flush);

        return $publicExport;
    }

    public function delete(PublicExport $publicExport, bool $flush = true): bool
    {
        $this->entityManager->remove($publicExport);
        $this->flush($flush);

        return true;
    }
}
