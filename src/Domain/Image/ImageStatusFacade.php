<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AbstractAssetFileStatusFacade;
use AnzuSystems\CoreDamBundle\Domain\Image\FileProcessor\DefaultRoiProcessor;
use AnzuSystems\CoreDamBundle\Domain\Image\FileProcessor\ImageAttributesProcessor;
use AnzuSystems\CoreDamBundle\Domain\Image\FileProcessor\OptimalCropsProcessor;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Exception\DuplicateAssetFileException;
use AnzuSystems\CoreDamBundle\Exception\ImageManipulatorException;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmFinishDto;
use AnzuSystems\CoreDamBundle\Model\Dto\File\File;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
use League\Flysystem\FilesystemException;

/**
 * @method ImageFile finishUpload(AssetAdmFinishDto $assetFinishDto, AssetFile $assetFile)
 */
final class ImageStatusFacade extends AbstractAssetFileStatusFacade
{
    public function __construct(
        private readonly ImageAttributesProcessor $imageAttributesProcessor,
        private readonly OptimalCropsProcessor $optimalCropsProcessor,
        private readonly DefaultRoiProcessor $defaultRoiProcessor,
        private readonly ImageFileRepository $imageFileRepository
    ) {
    }

    public static function getDefaultKeyName(): string
    {
        return ImageFile::class;
    }

    /**
     * @throws ImageManipulatorException
     * @throws FilesystemException
     */
    protected function processAssetFile(AssetFile $assetFile, File $file): AssetFile
    {
        $this->imageAttributesProcessor->process($assetFile, $file);
        $this->optimalCropsProcessor->process($assetFile, $file);
        $this->defaultRoiProcessor->process($assetFile, $file);

        return $assetFile;
    }

    protected function checkDuplicate(AssetFile $assetFile): void
    {
        $originAsset = $this->imageFileRepository->findProcessedByChecksum($assetFile->getAssetAttributes()->getChecksum());
        if ($originAsset) {
            throw new DuplicateAssetFileException($originAsset, $assetFile);
        }
    }
}
