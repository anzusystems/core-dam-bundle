<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\ImagePreview;

/**
 * @extends AbstractAnzuRepository<ImagePreview>
 *
 * @method ImagePreview|null find($id, $lockMode = null, $lockVersion = null)
 * @method ImagePreview|null findOneBy($id, $lockMode = null, $lockVersion = null)
 * @method ImagePreview|null findProcessedById(string $id)
 * @method ImagePreview|null findProcessedByIdAndFilename(string $id, string $slug)
 */
final class ImagePreviewRepository extends AbstractAssetFileRepository
{
    protected function getEntityClass(): string
    {
        return ImagePreview::class;
    }
}
