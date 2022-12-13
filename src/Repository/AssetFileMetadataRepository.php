<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\AssetFileMetadata;

/**
 * @extends AbstractAnzuRepository<AssetFileMetadata>
 *
 * @method AssetFileMetadata|null find($id, $lockMode = null, $lockVersion = null)
 * @method AssetFileMetadata|null findOneBy($id, $lockMode = null, $lockVersion = null)
 * @method AssetFileMetadata|null findProcessedById(string $id)
 * @method AssetFileMetadata|null findProcessedByIdAndFilename(string $id, string $slug)
 */
final class AssetFileMetadataRepository extends AbstractAnzuRepository
{
    protected function getEntityClass(): string
    {
        return AssetFileMetadata::class;
    }
}
