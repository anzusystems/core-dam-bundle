<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image\Crop;

use AnzuSystems\CoreDamBundle\Domain\Configuration\AllowListConfiguration;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\RegionOfInterest;
use AnzuSystems\CoreDamBundle\Exception\ImageManipulatorException;
use AnzuSystems\CoreDamBundle\Exception\InvalidCropException;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\Crop\RequestedCropDto;
use League\Flysystem\FilesystemException;

final readonly class CropFacade
{
    public function __construct(
        private readonly CropFactory $cropFactory,
        private readonly CropProcessor $cropProcessor,
        private readonly AllowListConfiguration $allowListConfiguration,
    ) {
    }

    /**
     * @throws ImageManipulatorException
     * @throws FilesystemException
     * @throws InvalidCropException
     */
    public function applyCropPayload(
        ImageFile $image,
        RequestedCropDto $cropPayload,
        RegionOfInterest $roi,
    ): string {
        $this->validateCrop($cropPayload);
        $crop = $this->cropFactory->prepareImageCrop($roi, $cropPayload, $image);

        return $this->cropProcessor->applyCrop($image, $crop);
    }

    /**
     * @throws InvalidCropException
     */
    private function validateCrop(RequestedCropDto $cropDto): void
    {
        $allowList = $this->allowListConfiguration->getListByDomain();

        if (
            $cropDto->getQuality() &&
            false === in_array($cropDto->getQuality(), $allowList->getQualityAllowList(), true)
        ) {
            throw new InvalidCropException();
        }

        foreach ($allowList->getCrops() as $crop) {
            if (
                $cropDto->getRequestWidth() === $crop['width'] &&
                $cropDto->getRequestHeight() === $crop['height']
            ) {
                return;
            }
        }

        throw new InvalidCropException();
    }
}
