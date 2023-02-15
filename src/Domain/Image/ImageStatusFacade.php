<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AbstractAssetFileStatusFacade;
use AnzuSystems\CoreDamBundle\Domain\Image\FileProcessor\DefaultRoiProcessor;
use AnzuSystems\CoreDamBundle\Domain\Image\FileProcessor\MostDominantColorProcessor;
use AnzuSystems\CoreDamBundle\Domain\Image\FileProcessor\OptimalCropsProcessor;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Exception\DuplicateAssetFileException;
use AnzuSystems\CoreDamBundle\Exception\ImageManipulatorException;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmFinishDto;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use InvalidArgumentException;
use League\Flysystem\FilesystemException;

/**
 * @method ImageFile finishUpload(AssetAdmFinishDto $assetFinishDto, AssetFile $assetFile)
 */
final class ImageStatusFacade extends AbstractAssetFileStatusFacade
{
    public function __construct(
        private readonly MostDominantColorProcessor $mostDominantColorProcessor,
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
     * @throws SerializerException
     * @throws InvalidArgumentException
     */
    protected function processAssetFile(AssetFile $assetFile, AdapterFile $file): AssetFile
    {
        if (false === ($assetFile instanceof ImageFile)) {
            throw new InvalidArgumentException('Asset type must be a type of image');
        }

        $this->mostDominantColorProcessor->process($assetFile, $file);
        $this->optimalCropsProcessor->process($assetFile, $file);
        $this->defaultRoiProcessor->process($assetFile, $file);

        return $assetFile;
    }

    protected function checkDuplicate(AssetFile $assetFile): void
    {
        $originAsset = $this->imageFileRepository->findProcessedByChecksumAndLicence(
            checksum: $assetFile->getAssetAttributes()->getChecksum(),
            licence: $assetFile->getLicence(),
        );
        if ($originAsset) {
            throw new DuplicateAssetFileException($originAsset, $assetFile);
        }
    }
}
