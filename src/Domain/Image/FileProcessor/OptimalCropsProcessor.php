<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image\FileProcessor;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\ImageFileOptimalResize\OptimalResizeFactory;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Exception\ImageManipulatorException;
use AnzuSystems\CoreDamBundle\Helper\Math;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use League\Flysystem\FilesystemException as FilesystemExceptionAlias;

final class OptimalCropsProcessor
{
    public function __construct(
        private readonly ConfigurationProvider $configurationProvider,
        private readonly OptimalResizeFactory $optimalResizeFactory,
    ) {
    }

    /**
     * @param ImageFile $assetFile
     *
     * @throws ImageManipulatorException
     * @throws FilesystemExceptionAlias
     */
    public function process(AssetFile $assetFile, AdapterFile $file): AssetFile
    {
        $mainCrop = $this->optimalResizeFactory->createMainCrop($assetFile, $file);
        // Main crop can be rotated (it respects exif orientation metadata) but have original resolution
        $this->setSizeAttributes($assetFile, $mainCrop->getWidth(), $mainCrop->getHeight());

        foreach ($this->configurationProvider->getImageOptimalResizes() as $imageOptimalResize) {
            if ($this->shouldCreateCrop($assetFile, $imageOptimalResize)) {
                $this->optimalResizeFactory->createCrop($assetFile, $file, $imageOptimalResize);
            }
        }

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

    private function shouldCreateCrop(ImageFile $image, int $size): bool
    {
        return $image->getImageAttributes()->getWidth() > $size && $image->getImageAttributes()->getHeight() > $size;
    }
}
