<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\DocumentFile;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;

/**
 * @extends AbstractAnzuRepository<DocumentFile>
 *
 * @method DocumentFile|null find($id, $lockMode = null, $lockVersion = null)
 * @method DocumentFile|null findOneBy($id, $lockMode = null, $lockVersion = null)
 * @method DocumentFile|null findProcessedById(string $id)
 * @method DocumentFile|null findProcessedByIdAndFilename(string $id, string $slug)
 */
final class VideoFileRepository extends AbstractAssetFileRepository
{
    protected function getEntityClass(): string
    {
        return VideoFile::class;
    }
}
