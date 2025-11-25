<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\ImagePreview;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * @extends AbstractAnzuRepository<ImagePreview>
 *
 * @method ImagePreview|null find($id, $lockMode = null, $lockVersion = null)
 * @method ImagePreview|null findOneBy($id, $lockMode = null, $lockVersion = null)
 */
final class ImagePreviewRepository extends AbstractAnzuRepository
{
    /**
     * @return Collection<int, ImagePreview>
     */
    public function findByImage(string $imageFileId): Collection
    {
        return new ArrayCollection(
            $this->findBy([
                'imageFile' => $imageFileId,
            ])
        );
    }

    protected function getEntityClass(): string
    {
        return ImagePreview::class;
    }
}
