<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Domain\ImageFileOptimalResize\OptimalResizeManager;
use AnzuSystems\CoreDamBundle\Domain\ImagePreview\ImagePreviewManager;
use AnzuSystems\CoreDamBundle\Domain\RegionOfInterest\RegionOfInterestManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\RegionOfInterest;

/**
 * @extends AssetFileManager<ImageFile>
 */
final class ImageManager extends AssetFileManager
{
    public function __construct(
        private readonly RegionOfInterestManager $regionOfInterestManager,
        private readonly OptimalResizeManager $optimalResizeManager,
        private readonly ImagePreviewManager $imagePreviewManager,
    ) {
    }

    public function addRegionOfInterest(
        ImageFile $image,
        RegionOfInterest $regionOfInterest,
        bool $flush = true
    ): ImageFile {
        $image->getRegionsOfInterest()->add($regionOfInterest);
        $regionOfInterest->setImage($image);
        $this->flush($flush);

        return $image;
    }

    /**
     * @param ImageFile $assetFile
     */
    protected function deleteAssetFileRelations(AssetFile $assetFile): void
    {
        $this->regionOfInterestManager->deleteByImage($assetFile);
        $this->optimalResizeManager->deleteByImage($assetFile);
        $this->imagePreviewManager->deleteByImage($assetFile);
    }
}
