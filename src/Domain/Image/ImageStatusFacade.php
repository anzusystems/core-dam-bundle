<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AbstractAssetFileStatusFacade;
use AnzuSystems\CoreDamBundle\Domain\Image\FileProcessor\DefaultRoiProcessor;
use AnzuSystems\CoreDamBundle\Domain\Image\FileProcessor\OptimalCropsProcessor;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Exception\ImageManipulatorException;
use AnzuSystems\CoreDamBundle\Image\VispImageManipulator;
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
        private readonly OptimalCropsProcessor $optimalCropsProcessor,
        private readonly DefaultRoiProcessor $defaultRoiProcessor,
        private readonly ImageFileRepository $imageFileRepository,
        private readonly VispImageManipulator $imageManipulator,
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
        $imageFile = $this->getImage($assetFile);
        $this->imageManipulator->loadFile($file->getRealPath());

        // TODo most dominant color memory problems
        $imageFile->getImageAttributes()
            ->setAnimated($this->imageManipulator->isAnimated())
//            ->setMostDominantColor($this->imageManipulator->getMostDominantColor())
        ;

        $this->optimalCropsProcessor->process($imageFile, $file);
        $this->defaultRoiProcessor->process($imageFile, $file);
        $this->imageManipulator->clean();

        return $imageFile;
    }

    protected function checkDuplicate(AssetFile $assetFile): ?AssetFile
    {
        return $this->imageFileRepository->findProcessedByChecksumAndLicence(
            checksum: $assetFile->getAssetAttributes()->getChecksum(),
            licence: $assetFile->getLicence(),
        );
    }

    private function getImage(AssetFile $assetFile): ImageFile
    {
        if (false === ($assetFile instanceof ImageFile)) {
            throw new InvalidArgumentException('Asset type must be a type of image');
        }

        return $assetFile;
    }
}
