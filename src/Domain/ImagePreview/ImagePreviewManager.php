<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\ImagePreview;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\ImagePreview;
use AnzuSystems\CoreDamBundle\Repository\ImagePreviewRepository;

final class ImagePreviewManager extends AbstractManager
{
    public function __construct(
        private readonly ImagePreviewRepository $imagePreviewRepository
    ) {
    }

    public function create(ImagePreview $imagePreview, bool $flush = true): ImagePreview
    {
        $this->trackCreation($imagePreview);
        $this->entityManager->persist($imagePreview);
        $this->flush($flush);

        return $imagePreview;
    }

    public function updateExisting(ImagePreview $imagPreview, bool $flush = true): ImagePreview
    {
        $this->trackModification($imagPreview);
        $this->flush($flush);

        return $imagPreview;
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

    public function deleteByImage(ImageFile $imageFile): void
    {
        $previews = $this->imagePreviewRepository->findByImage((string) $imageFile->getId());

        foreach ($previews as $preview) {
            $this->delete($preview, false);
        }
    }

    public function getNewImagePreview(?ImagePreview $oldImagePreview, ?ImagePreview $newImagePreview): ?ImagePreview
    {
        if ($oldImagePreview && $newImagePreview) {
            $this->update(
                imagPreview: $oldImagePreview,
                newImagePreview: $newImagePreview,
                flush: false
            );

            return $oldImagePreview;
        }

        if ($oldImagePreview) {
            $imagePreview = $oldImagePreview;
            $this->delete($imagePreview);

            return null;
        }

        if ($newImagePreview) {
            $this->create(
                imagePreview: $newImagePreview,
                flush: false
            );

            return $newImagePreview;
        }

        return null;
    }
}
