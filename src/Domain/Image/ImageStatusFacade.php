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
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
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
    protected function processAssetFile(AssetFile $assetFile, AdapterFile $file): AssetFile
    {
        $this->damLogger->info('AssetFileProcess', sprintf('Asset file (%s) processing attributes', (string) $assetFile->getId()));
        $this->imageAttributesProcessor->process($assetFile, $file);
        $this->damLogger->info('AssetFileProcess', sprintf('Asset file (%s) processing crops', (string) $assetFile->getId()));
        $this->optimalCropsProcessor->process($assetFile, $file);
        $this->damLogger->info('AssetFileProcess', sprintf('Asset file (%s) processing default rois', (string) $assetFile->getId()));
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
