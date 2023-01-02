<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\ImageFileOptimalResize;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Controller\AbstractImageController;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFileOptimalResize;
use AnzuSystems\CoreDamBundle\Exception\ImageManipulatorException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\FileSystem\NameGenerator\NameGenerator;
use AnzuSystems\CoreDamBundle\Image\ImageManipulatorInterface;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use League\Flysystem\FilesystemException as FilesystemExceptionAlias;

final class OptimalResizeFactory extends AbstractManager
{
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
    public function createMainCrop(ImageFile $asset, AdapterFile $file): void
    {
        $resize = $this->createCrop(
            $asset,
            $file,
            max($asset->getImageAttributes()->getWidth(), $asset->getImageAttributes()->getHeight())
        );
        $resize->setOriginal(true);
    }

    /**
     * @throws ImageManipulatorException
     * @throws FilesystemExceptionAlias
     */
    public function createCrop(ImageFile $imageFile, AdapterFile $file, int $size): ImageFileOptimalResize
    {
        $optimalResize = new ImageFileOptimalResize();

        $tmpPath = $this->nameGenerator->generatePath(AbstractImageController::CROP_EXTENSION)->getRelativePath();
        $storagePath = $this->createOptimalCropPath($imageFile, $size, $imageFile->getImageAttributes()->getRotation());

        $tmpFilesystem = $this->fileSystemProvider->getTmpFileSystem();
        $assetFileSystem = $this->fileSystemProvider->getFilesystemByStorable($imageFile);

        $this->imageManipulator->loadThumbnail($file->getRealPath(), $size);
        $this->imageManipulator->autorotate();

        $tmpFilesystem->ensureDirectory($tmpPath);
        $this->imageManipulator->writeToFile($tmpFilesystem->extendPath($tmpPath), false);

        $assetFileSystem->writeStream($storagePath, $tmpFilesystem->readStream($tmpPath));

        $optimalResize
            ->setWidth($this->imageManipulator->getWidth())
            ->setHeight($this->imageManipulator->getHeight())
            ->setFilePath($storagePath)
            ->setRequestedSize($size);

        $optimalResize->setImage($imageFile);
        $imageFile->getResizes()->add($optimalResize);

        return $this->optimalResizeManager->create($optimalResize, false);
    }
}
