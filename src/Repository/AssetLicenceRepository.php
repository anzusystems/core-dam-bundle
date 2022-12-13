<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\AssetLicence;

/**
 * @extends AbstractAnzuRepository<AssetLicence>
 *
 * @method AssetLicence|null find($id, $lockMode = null, $lockVersion = null)
 * @method AssetLicence|null findOneBy($id, $lockMode = null, $lockVersion = null)
 * @method AssetLicence|null findProcessedById(string $id)
 * @method AssetLicence|null findProcessedByIdAndFilename(string $id, string $slug)
 */
final class AssetLicenceRepository extends AbstractAnzuRepository
{
    protected function getEntityClass(): string
    {
        return AssetLicence::class;
    }
}
