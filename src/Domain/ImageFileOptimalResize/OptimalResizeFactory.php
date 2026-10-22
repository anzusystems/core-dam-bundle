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
    public const int OPTIMAL_RESIZE_QUALITY = 90;

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
            originPath: $imageFile->getAssetAttributes()->getFilePath(),
            fileNameSuffix: "{$angle}_{$size}",
            removeOldSuffix: true,
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
        $optimalResize = $this->buildResize($imageFile, $size);

        $this->processOptimalResize($optimalResize, $file);
        $imageFile->getResizes()->add($optimalResize);

        return $this->optimalResizeManager->create($optimalResize, false);
    }

    /**
     * The largest size is made from the original, every smaller one from the largest resize kept in memory.
     * The encoded resize file must not be the source (it would add a second lossy JPEG generation) and the main crop
     * must not be kept in memory (original resolution, ~120 MB), so only the largest resize is held.
     *
     * @param list<int> $sizes
     *
     * @throws ImageManipulatorException
     * @throws FilesystemExceptionAlias
     */
    public function createCrops(ImageFile $imageFile, AdapterFile $file, array $sizes): void
    {
        if ([] === $sizes) {
            return;
        }

        rsort($sizes);
        $largestSize = array_shift($sizes);
        $largest = $this->buildResize($imageFile, $largestSize);
        $resizes = [$largest];

        try {
            $this->imageManipulator->loadThumbnail($file->getRealPath(), $largestSize);
            $this->imageManipulator->autorotate();
            if ([] !== $sizes) {
                $this->imageManipulator->retain();
            }
            $this->writeResize($largest);

            foreach ($sizes as $size) {
                $resize = $this->buildResize($imageFile, $size);
                $this->imageManipulator->loadRetainedThumbnail($size);
                $this->writeResize($resize);
                $resizes[] = $resize;
            }
        } finally {
            $this->imageManipulator->releaseRetained();
        }

        foreach (array_reverse($resizes) as $resize) {
            $imageFile->getResizes()->add($resize);
            $this->optimalResizeManager->create($resize, false);
        }
    }

    public function processOptimalResize(ImageFileOptimalResize $resize, AdapterFile $file): void
    {
        $this->imageManipulator->loadThumbnail($file->getRealPath(), $resize->getRequestedSize());
        $this->imageManipulator->autorotate();

        $this->writeResize($resize);
    }

    private function buildResize(ImageFile $imageFile, int $size): ImageFileOptimalResize
    {
        return (new ImageFileOptimalResize())
            ->setRequestedSize($size)
            ->setImage($imageFile)
        ;
    }

    private function writeResize(ImageFileOptimalResize $resize): void
    {
        $this->imageManipulator->setQuality(self::OPTIMAL_RESIZE_QUALITY);

        // Prepare path and folder for Visp to write crop file
        $tmpFilesystem = $this->fileSystemProvider->getTmpFileSystem();
        $tmpPath = $tmpFilesystem->getTmpFileName(
            $this->fileHelper->guessExtension(CropProcessor::getCropMimeType($resize->getImage()))
        );

        $tmpFilesystem->ensureDirectory($tmpPath);
        // Write rotated crop file
        $this->imageManipulator->writeToFile($tmpFilesystem->extendPath($tmpPath), false);
        // Write file to target storage
        $assetFileSystem = $this->fileSystemProvider->getFilesystemByStorable($resize->getImage());
        $storagePath = $this->createOptimalCropPath(
            $resize->getImage(),
            $resize->getRequestedSize(),
            $resize->getImage()->getImageAttributes()->getRotation()
        );

        $assetFileSystem->writeStream($storagePath, $tmpFilesystem->readStream($tmpPath));

        $resize
            ->setWidth($this->imageManipulator->getWidth())
            ->setHeight($this->imageManipulator->getHeight())
            ->setFilePath($storagePath)
        ;

        $this->imageManipulator->clean();
    }
}
