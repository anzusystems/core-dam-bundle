<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\ImageFileOptimalResize;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Domain\Image\Crop\CropProcessor;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFileOptimalResize;
use AnzuSystems\CoreDamBundle\Exception\ImageManipulatorException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\FileSystem\NameGenerator\NameGenerator;
use AnzuSystems\CoreDamBundle\Image\ImageManipulatorInterface;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Traits\FileHelperTrait;
use League\Flysystem\FilesystemException as FilesystemExceptionAlias;

final class OptimalResizeFactory extends AbstractManager
{
    use FileHelperTrait;

    public function __construct(
        private readonly FileSystemProvider $fileSystemProvider,
        private readonly NameGenerator $nameGenerator,
        private readonly ImageManipulatorInterface $imageManipulator,
        private readonly OptimalResizeManager $optimalResizeManager,
    ) {
    }

    public function createOptimalCropPath(ImageFile $imageFile, int $size, float $angle): string
    {
        return $this->nameGenerator->alternatePath(
            $imageFile->getAssetAttributes()->getFilePath(),
            "{$angle}_{$size}"
        )->getRelativePath();
    }

    /**
     * @throws ImageManipulatorException
     * @throws FilesystemExceptionAlias
     */
    public function createMainCrop(ImageFile $asset, AdapterFile $file): ImageFileOptimalResize
    {
        [$width, $height] = getimagesize($file->getRealPath());

        return $this->createCrop(
            imageFile: $asset,
            file: $file,
            size: max($width, $height)
        )->setOriginal(true);
    }

    /**
     * @throws ImageManipulatorException
     * @throws FilesystemExceptionAlias
     */
    public function createCrop(ImageFile $imageFile, AdapterFile $file, int $size): ImageFileOptimalResize
    {
        $optimalResize = new ImageFileOptimalResize();
        // load file to Visp
        $this->imageManipulator->loadThumbnail($file->getRealPath(), $size);
        $this->imageManipulator->autorotate();

        // Prepare path and folder for Visp to write crop file
        $tmpFilesystem = $this->fileSystemProvider->getTmpFileSystem();
        $tmpPath = $tmpFilesystem->getTmpFileName(
            $this->fileHelper->guessExtension(CropProcessor::getCropMimeType($imageFile))
        );

        $tmpFilesystem->ensureDirectory($tmpPath);
        // Write rotated crop file
        $this->imageManipulator->writeToFile($tmpFilesystem->extendPath($tmpPath), false);
        //        // Write file to target storage
        $assetFileSystem = $this->fileSystemProvider->getFilesystemByStorable($imageFile);
        $storagePath = $this->createOptimalCropPath($imageFile, $size, $imageFile->getImageAttributes()->getRotation());
        $assetFileSystem->writeStream($storagePath, $tmpFilesystem->readStream($tmpPath));

        $optimalResize
            ->setWidth($this->imageManipulator->getWidth())
            ->setHeight($this->imageManipulator->getHeight())
            ->setFilePath($storagePath)
            ->setRequestedSize($size);

        $optimalResize->setImage($imageFile);
        $imageFile->getResizes()->add($optimalResize);

        $this->imageManipulator->clean();

        return $this->optimalResizeManager->create($optimalResize, false);
    }
}
