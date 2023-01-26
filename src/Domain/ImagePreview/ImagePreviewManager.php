<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\ImagePreview;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\ImagePreview;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ImagePreviewableInterface;

final class ImagePreviewManager extends AbstractManager
{
    public function create(ImagePreview $imagePreview, bool $flush = true): ImagePreview
    {
        $this->trackCreation($imagePreview);
        $this->entityManager->persist($imagePreview);
        $this->flush($flush);

        return $imagePreview;
    }

    public function update(ImagePreview $imagPreview, ImagePreview $newImagePreview, bool $flush = true): ImagePreview
    {
        $this->trackModification($imagPreview);
        $imagPreview
            ->setPosition($newImagePreview->getPosition())
            ->setImageFile($newImagePreview->getImageFile())
        ;
        $this->flush($flush);

        return $imagPreview;
    }

    public function delete(ImagePreview $imagePreview, bool $flush = true): bool
    {
        $this->entityManager->remove($imagePreview);
        $this->flush($flush);

        return true;
    }

    public function setImagePreviewRelation(
        ImagePreviewableInterface $imagePreviewable,
        ImagePreviewableInterface $newImagePreviewable
    ): void {
        if ($imagePreviewable->getImagePreview() && $newImagePreviewable->getImagePreview()) {
            $this->update(
                imagPreview: $imagePreviewable->getImagePreview(),
                newImagePreview: $imagePreviewable->getImagePreview(),
                flush: false
            );

            return;
        }

        if ($imagePreviewable->getImagePreview() && null === $newImagePreviewable->getImagePreview()) {
            $this->delete($imagePreviewable->getImagePreview());
            $imagePreviewable->setImagePreview(null);

            return;
        }

        $imagePreviewable->setImagePreview($newImagePreviewable->getImagePreview());
        $this->create(
            imagePreview: $imagePreviewable->getImagePreview(),
            flush: false
        );
    }
}
