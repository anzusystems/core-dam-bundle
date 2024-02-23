<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\AssetLicenceGroup;

/**
 * @extends AbstractAnzuRepository<AssetLicenceGroup>
 *
 * @method AssetLicenceGroup|null find($id, $lockMode = null, $lockVersion = null)
 * @method AssetLicenceGroup|null findOneBy($id, $lockMode = null, $lockVersion = null)
 */
final class AssetLicenceGroupRepository extends AbstractAnzuRepository
{
    protected function getEntityClass(): string
    {
        return AssetLicenceGroup::class;
    }
}
