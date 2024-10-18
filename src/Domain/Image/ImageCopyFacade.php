<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFilePositionFacade;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageCopyDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageCopyResultDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCopyResult;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
use Doctrine\Common\Collections\Collection;

final class ImageCopyFacade
{
    public function __construct(
        private readonly ImageFileRepository $imageFileRepository,
    )
    {

    }

    /**
     * @param Collection<int, ImageCopyDto> $collection
     */
    public function copyList(Collection $collection)
    {
        foreach ($collection as $imageCopyDto) {
            $resDto = $this->prepareCopyResult($imageCopyDto);
        }
    }

    private function prepareCopyResult(ImageCopyDto $copyDto): ImageCopyResultDto
    {
        $foundAssets = [];
        foreach ($copyDto->getAsset()->getSlots() as $slot) {
            $foundAssetFile = $this->imageFileRepository->findProcessedByChecksumAndLicence(
                checksum: $slot->getAssetFile()->getAssetAttributes()->getChecksum(),
                licence: $copyDto->getTargetAssetLicence()
            );

            $foundAssets[$foundAssetFile->getAsset()->getId()] = $foundAssetFile->getAsset();
        }

        $firstFoundAsset = $foundAssets[0] ?? null;

        if (null === $firstFoundAsset) {
            return ImageCopyResultDto::create(
                asset: $copyDto->getAsset(),
                targetAssetLicence: $copyDto->getTargetAssetLicence(),
                result: AssetFileCopyResult::Copying
            );
        }

        if (count($foundAssets) > 1 || false === $firstFoundAsset->hasSameFilesIdentityString($copyDto->getAsset())) {
            return ImageCopyResultDto::create(
                asset: $copyDto->getAsset(),
                targetAssetLicence: $copyDto->getTargetAssetLicence(),
                result: AssetFileCopyResult::NotAllowed,
                assetConflicts: array_values($foundAssets)
            );
        }

        return ImageCopyResultDto::create(
            asset: $copyDto->getAsset(),
            targetAssetLicence: $copyDto->getTargetAssetLicence(),
            result: AssetFileCopyResult::Exists,
            mainAssetFile: $firstFoundAsset->getMainFile(),
            assetConflicts: array_values($foundAssets)
        );
    }
}
