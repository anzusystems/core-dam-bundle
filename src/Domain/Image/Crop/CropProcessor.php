<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image\Crop;

use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFileOptimalResize;
use AnzuSystems\CoreDamBundle\Exception\DomainException;
use AnzuSystems\CoreDamBundle\Exception\ImageManipulatorException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Image\Filter\AutoRotateFilter;
use AnzuSystems\CoreDamBundle\Image\Filter\CropFilter;
use AnzuSystems\CoreDamBundle\Image\Filter\FilterStack;
use AnzuSystems\CoreDamBundle\Image\Filter\QualityFilter;
use AnzuSystems\CoreDamBundle\Image\Filter\ResizeFilter;
use AnzuSystems\CoreDamBundle\Image\ImageManipulatorInterface;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageCropDto;
use AnzuSystems\CoreDamBundle\Traits\FileHelperTrait;
use League\Flysystem\FilesystemException;

final class CropProcessor
{
    use FileHelperTrait;

    public const string DEFAULT_MIME_TYPE = 'image/jpeg';

    public function __construct(
        private readonly FileSystemProvider $fileSystemProvider,
        private readonly ImageManipulatorInterface $imageManipulator,
        private readonly CropCache $cropCache,
    ) {
    }

    /**
     * @throws ImageManipulatorException
     * @throws FilesystemException
     * @throws DomainException
     */
    public function applyCrop(ImageFile $image, ImageCropDto $imageCrop): string
    {
        if ($this->cropCache->isStored($image, $imageCrop)) {
            return $this->cropCache->get($image, $imageCrop);
        }

        $optimalResize = $this->getOptimalResize($image, $imageCrop);
        $recalculatedCrop = $this->recalculateImageCrop(
            image: $image,
            optimalResize: $optimalResize,
            imageCrop: $imageCrop
        );

        $fileStream = $this->fileSystemProvider
            ->getFilesystemByStorable($image)
            ->read($optimalResize->getFilePath());

        $this->imageManipulator->loadContent($fileStream);

        $this->imageManipulator->applyFilterStack(
            new FilterStack([
                new AutoRotateFilter(true),
                new CropFilter(
                    $recalculatedCrop->getPointX(),
                    $recalculatedCrop->getPointY(),
                    $recalculatedCrop->getWidth(),
                    $recalculatedCrop->getHeight()
                ),
                new ResizeFilter($recalculatedCrop->getRequestWidth(), $recalculatedCrop->getRequestHeight()),
                new QualityFilter($recalculatedCrop->getQuality()),
            ])
        );

        $content = $this->imageManipulator->getContent($this->fileHelper->guessExtension(self::DEFAULT_MIME_TYPE));
        $this->cropCache->store($image, $imageCrop, $content);

        return $content;
    }

    /**
     * @throws DomainException
     */
    private function getOptimalResize(ImageFile $imageFile, ImageCropDto $imageCrop): ImageFileOptimalResize
    {
        $lastResize = null;
        foreach ($imageFile->getResizes() as $imageResize) {
            $lastResize = $imageResize;
            if (
                $imageResize->getWidth() >= $imageCrop->getRequestWidth() &&
                $imageResize->getHeight() >= $imageCrop->getRequestHeight()
            ) {
                return $imageResize;
            }
        }

        return $lastResize ?? throw new DomainException(
            sprintf(
                'Image (%s) optimal resize is missing',
                $imageFile->getId()
            )
        );
    }

    private function recalculateImageCrop(
        ImageFile $image,
        ImageFileOptimalResize $optimalResize,
        ImageCropDto $imageCrop
    ): ImageCropDto {
        $resizeRatio = $optimalResize->getWidth() / $image->getImageAttributes()->getWidth();

        return new ImageCropDto(
            pointX: (int) ($imageCrop->getPointX() * $resizeRatio),
            pointY: (int) ($imageCrop->getPointY() * $resizeRatio),
            width: min((int) ($imageCrop->getWidth() * $resizeRatio), $optimalResize->getWidth()),
            height: min((int) ($imageCrop->getHeight() * $resizeRatio), $optimalResize->getHeight()),
            requestWidth: $imageCrop->getRequestWidth(),
            requestHeight: $imageCrop->getRequestHeight(),
            quality: $imageCrop->getQuality(),
        );
    }
}
