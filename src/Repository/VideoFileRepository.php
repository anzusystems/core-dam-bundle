<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\VideoFile;

/**
 * @extends AbstractAnzuRepository<VideoFile>
 *
 * @method VideoFile|null find($id, $lockMode = null, $lockVersion = null)
 * @method VideoFile|null findOneBy($id, $lockMode = null, $lockVersion = null)
 * @method VideoFile|null findProcessedById(string $id)
 * @method VideoFile|null findProcessedByIdAndFilename(string $id, string $slug)
 */
final class VideoFileRepository extends AbstractAssetFileRepository
{
    protected function getEntityClass(): string
    {
        return VideoFile::class;
    }
}
