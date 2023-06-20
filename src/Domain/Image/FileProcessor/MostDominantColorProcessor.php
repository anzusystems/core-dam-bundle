<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image\FileProcessor;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Exception\ImageManipulatorException;
use AnzuSystems\CoreDamBundle\Image\VispImageManipulator;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\SerializerBundle\Exception\SerializerException;

final class MostDominantColorProcessor
{
    public function __construct(
        private readonly VispImageManipulator $imageManipulator,
    ) {
    }

    /**
     * @param ImageFile $assetFile
     *
     * @throws ImageManipulatorException
     * @throws SerializerException
     */
    public function process(AssetFile $assetFile, AdapterFile $file): AssetFile
    {
        $this->imageManipulator->loadFile($file->getRealPath());
        $assetFile->getImageAttributes()->setMostDominantColor(
            $this->imageManipulator->getMostDominantColor()
        );

        return $assetFile;
    }
}
