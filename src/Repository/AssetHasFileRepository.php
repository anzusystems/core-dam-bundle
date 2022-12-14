<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\AssetHasFile;

/**
 * @extends AbstractAnzuRepository<AssetHasFile>
 *
 * @method AssetHasFile|null find($id, $lockMode = null, $lockVersion = null)
 * @method AssetHasFile|null findOneBy(array $criteria, array $orderBy = null)
 */
final class AssetHasFileRepository extends AbstractAnzuRepository
{
    protected function getEntityClass(): string
    {
        return AssetHasFile::class;
    }
}
