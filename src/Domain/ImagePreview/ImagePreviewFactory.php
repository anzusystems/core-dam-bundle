<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\ImagePreview;

use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\ImagePreview;

final readonly class ImagePreviewFactory
{
    public function __construct(
        private ImagePreviewManager $imagePreviewManager
    ) {
    }

    public function createFromImageFile(ImageFile $imageFile, int $position = 0, bool $flush = true): ImagePreview
    {
        $imagePreview = (new ImagePreview())
            ->setPosition($position)
            ->setImageFile($imageFile);

        return $this->imagePreviewManager->create($imagePreview, $flush);
    }
}
