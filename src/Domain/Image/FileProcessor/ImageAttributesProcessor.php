<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image\FileProcessor;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Exception\ImageManipulatorException;
use AnzuSystems\CoreDamBundle\Helper\Math;
use AnzuSystems\CoreDamBundle\Image\VispImageManipulator;
use AnzuSystems\CoreDamBundle\Model\Dto\File\File;

final class ImageAttributesProcessor
{
    public function __construct(
        private readonly VispImageManipulator $imageManipulator
    ) {
    }

    /**
     * @param ImageFile $assetFile
     *
     * @throws ImageManipulatorException
     */
    public function process(AssetFile $assetFile, File $file): AssetFile
    {
        [$width, $height] = getimagesize($file->getRealPath());
        $this->setSizeAttributes($assetFile, $width, $height);

        $this->imageManipulator->loadFile($file->getRealPath());
        $assetFile->getImageAttributes()->setMostDominantColor(
            $this->imageManipulator->getMostDominantColor()
        );

        return $assetFile;
    }

    public function setSizeAttributes(ImageFile $assetFile, int $width, int $height): ImageFile
    {
        $gcd = Math::getGreatestCommonDivisor($width, $height);

        $assetFile->getImageAttributes()
            ->setRatioWidth((int) ($width / $gcd))
            ->setRatioHeight((int) ($height / $gcd))
            ->setWidth($width)
            ->setHeight($height);

        return $assetFile;
    }
}
