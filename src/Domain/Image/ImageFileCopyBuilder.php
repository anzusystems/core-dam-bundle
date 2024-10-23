<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image;

use AnzuSystems\CoreDamBundle\Domain\ImageFileOptimalResize\ImageFileOptimalResizeCopyBuilder;
use AnzuSystems\CoreDamBundle\Domain\RegionOfInterest\RegionOfInterestManager;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use League\Flysystem\FilesystemException;

final readonly class ImageFileCopyBuilder
{
    public function __construct(
        private RegionOfInterestManager $regionOfInterestManager,
        private ImageFileOptimalResizeCopyBuilder $imageFileOptimalResizeCopyBuilder,
    ) {
    }

    /**
     * @throws FilesystemException
     */
    public function copy(ImageFile $imageFile, ImageFile $targetImageFile): void
    {
        $targetImageFile->setImageAttributes(clone $imageFile->getImageAttributes());
        $this->copyRegionsOfInterest($imageFile, $targetImageFile);
        $this->copyResizes($imageFile, $targetImageFile);
    }

    private function copyResizes(ImageFile $imageFile, ImageFile $targetImageFile): void
    {
        foreach ($imageFile->getResizes() as $resize) {
            $this->imageFileOptimalResizeCopyBuilder->copyResizeToImage($resize, $targetImageFile);
        }
    }

    private function copyRegionsOfInterest(ImageFile $imageFile, ImageFile $targetImageFile): void
    {
        foreach ($imageFile->getRegionsOfInterest() as $regionOfInterest) {
            $regionOfInterestCopy = $regionOfInterest->__copy();
            $regionOfInterestCopy->setImage($targetImageFile);
            $targetImageFile->getRegionsOfInterest()->add($regionOfInterestCopy);
            $this->regionOfInterestManager->create($regionOfInterestCopy, false);
        }
    }
}
