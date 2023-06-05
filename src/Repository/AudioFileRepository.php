<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\AudioFile;

/**
 * @template-extends AbstractAnzuRepository<AudioFile>
 * @method AudioFile|null find($id, $lockMode = null, $lockVersion = null)
 * @method AudioFile|null findOneBy($id, $lockMode = null, $lockVersion = null)
 * @method AudioFile|null findProcessedById(string $id)
 * @method AudioFile|null findProcessedByIdAndFilename(string $id, string $slug)
 */
final class AudioFileRepository extends AbstractAssetFileRepository
{
    protected function getEntityClass(): string
    {
        return AudioFile::class;
    }
}
