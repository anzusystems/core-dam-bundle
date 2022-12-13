<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image;

use AnzuSystems\CoreDamBundle\Controller\AbstractImageController;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileStash;
use AnzuSystems\CoreDamBundle\Domain\Image\FileProcessor\ImageAttributesProcessor;
use AnzuSystems\CoreDamBundle\Domain\ImageFileOptimalResize\OptimalResizeFactory;
use AnzuSystems\CoreDamBundle\Domain\RegionOfInterest\DefaultRegionOfInterestFactory;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFileOptimalResize;
use AnzuSystems\CoreDamBundle\Exception\ImageManipulatorException;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Image\Filter\FilterStack;
use AnzuSystems\CoreDamBundle\Image\Filter\RotateFilter;
use AnzuSystems\CoreDamBundle\Image\ImageManipulatorInterface;
use League\Flysystem\FilesystemException;

final class ImageRotator
{
    public const CLOCK_ANGLE = 360.0;
    public const MIRROR_ANGLE = 180.0;

    public function __construct(
        private readonly ImageManipulatorInterface $imageManipulator,
        private readonly DefaultRegionOfInterestFactory $regionOfInterestFactory,
        private readonly FileSystemProvider $fileSystemProvider,
        private readonly OptimalResizeFactory $optimalResizeFactory,
        private readonly FileStash $fileStash,
        private readonly ImageAttributesProcessor $attributesProcessor,
    ) {
    }

    /**
     * @throws FilesystemException
     * @throws ImageManipulatorException
     */
    public function rotateImage(ImageFile $image, float $angle): ImageFile
    {
        $newFileAngle = ($image->getImageAttributes()->getRotation() + $angle) % self::CLOCK_ANGLE;
        $image->getImageAttributes()->setRotation($newFileAngle);

        $resize = $this->rotateResizes($image, $angle);
        $this->attributesProcessor->setSizeAttributes($image, $resize->getWidth(), $resize->getHeight());

        foreach ($image->getRegionsOfInterest() as $roi) {
            $this->regionOfInterestFactory->recalculateRoi($image, $roi);
        }

        return $image;
    }

    /**
     * @throws FilesystemException
     * @throws ImageManipulatorException
     * @throws RuntimeException
     */
    private function rotateResizes(ImageFile $image, $angle): ImageFileOptimalResize
    {
        $originalResize = null;
        foreach ($image->getResizes() as $resize) {
            $this->rotateResize($resize, $angle, $image->getImageAttributes()->getRotation());
            if ($resize->isOriginal()) {
                $originalResize = $resize;
            }
        }

        if ($originalResize instanceof ImageFileOptimalResize) {
            return $originalResize;
        }

        throw new RuntimeException(
            sprintf(
                'Image id (%s) does not contain original optimal resize',
                $image->getId()
            )
        );
    }

    /**
     * @throws ImageManipulatorException
     * @throws FilesystemException
     */
    private function rotateResize(ImageFileOptimalResize $resize, float $angle, float $originalAngleDiff): void
    {
        $tmpFilesystem = $this->fileSystemProvider->getTmpFileSystem();
        $fileSystem = $this->fileSystemProvider->getFilesystemByStorable($resize);
        // rotate file in TMP filesystem
        $this->imageManipulator->loadContent($fileSystem->read($resize->getFilePath()));
        $this->imageManipulator->applyFilterStack(
            new FilterStack([
                new RotateFilter($angle),
            ])
        );
        $resize
            ->setWidth($this->imageManipulator->getWidth())
            ->setHeight($this->imageManipulator->getHeight());

        $path = $tmpFilesystem->getTmpFileName(AbstractImageController::CROP_EXTENSION);
        $this->imageManipulator->writeToFile($tmpFilesystem->extendPath($path));
        // move old file to stash and write new file
        $this->fileStash->add($resize);
        $resizePath = $this->optimalResizeFactory->createOptimalCropPath(
            $resize->getImage(),
            $resize->getRequestedSize(),
            $originalAngleDiff
        );
        if ($fileSystem->fileExists($resizePath)) {
            $fileSystem->delete($resizePath);
        }
        $fileSystem->writeStream($resizePath, $tmpFilesystem->readStream($path));
        $resize->setFilePath($resizePath);
    }
}
