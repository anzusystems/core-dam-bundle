<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\ImageFileOptimalResize;

/**
 * @extends AbstractAnzuRepository<ImageFileOptimalResize>
 *
 * @method ImageFileOptimalResize|null find($id, $lockMode = null, $lockVersion = null)
 * @method ImageFileOptimalResize|null findOneBy($id, $lockMode = null, $lockVersion = null)
 * @method ImageFileOptimalResize|null findProcessedById(string $id)
 * @method ImageFileOptimalResize|null findProcessedByIdAndFilename(string $id, string $slug)
 */
final class ImageFileOptimalResizeRepository extends AbstractAnzuRepository
{
    protected function getEntityClass(): string
    {
        return ImageFileOptimalResize::class;
    }
}
