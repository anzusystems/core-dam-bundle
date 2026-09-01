<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackFacade;
use AnzuSystems\CoreDamBundle\Domain\ImageFileOptimalResize\OptimalResizeManager;
use AnzuSystems\CoreDamBundle\Domain\ImagePreview\ImagePreviewManager;
use AnzuSystems\CoreDamBundle\Domain\RegionOfInterest\RegionOfInterestManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\RegionOfInterest;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageFileAdmDetailDto;

/**
 * @extends AssetFileManager<ImageFile>
 */
final class ImageManager extends AssetFileManager
{
    public function __construct(
        private readonly RegionOfInterestManager $regionOfInterestManager,
        private readonly OptimalResizeManager $optimalResizeManager,
        private readonly ImagePreviewManager $imagePreviewManager,
        private readonly ExtSystemCallbackFacade $extSystemCallbackFacade,
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

    public function updateImage(ImageFile $image, ImageFileAdmDetailDto $dto, bool $flush = true): ImageFile
    {
        $image->getFlags()
            ->setPublic($dto->getFlags()->isPublic())
            ->setSingleUse($dto->getFlags()->isSingleUse())
        ;

        $this->trackModification($image);
        $this->flush($flush);

        return $image;
    }

    /**
     * @param iterable<ImageFile> $assetFiles
     *
     * @return array<string, bool> image file id => can be removed
     */
    public function canBeRemovedBulk(iterable $assetFiles): array
    {
        $result = [];
        $toCheck = [];

        foreach ($assetFiles as $assetFile) {
            if (false === $assetFile->getExtSystem()->getFlags()->isCheckImageUsedOnDelete()) {
                $result[(string) $assetFile->getId()] = true;

                continue;
            }

            $toCheck[] = $assetFile;
        }

        if ([] === $toCheck) {
            return $result;
        }

        $usageMap = $this->extSystemCallbackFacade->isImageFileUsedBulk($toCheck);
        foreach ($toCheck as $assetFile) {
            $result[(string) $assetFile->getId()] = false === ($usageMap[(string) $assetFile->getId()] ?? true);
        }

        return $result;
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
