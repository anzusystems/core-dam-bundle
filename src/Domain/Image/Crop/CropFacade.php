<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image\Crop;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Configuration\AllowListConfiguration;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\RegionOfInterest;
use AnzuSystems\CoreDamBundle\Exception\ImageManipulatorException;
use AnzuSystems\CoreDamBundle\Exception\InvalidCropException;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\Crop\RequestedCropDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\RegionOfInterestRepository;
use Doctrine\ORM\NonUniqueResultException;
use DomainException;
use League\Flysystem\FilesystemException;

final readonly class CropFacade
{
    public function __construct(
        private CropFactory $cropFactory,
        private CropProcessor $cropProcessor,
        private AllowListConfiguration $allowListConfiguration,
        private RegionOfInterestRepository $regionOfInterestRepository,
        private ConfigurationProvider $configurationProvider
    ) {
    }

    /**
     * @throws FilesystemException
     * @throws ImageManipulatorException
     * @throws InvalidCropException
     * @throws NonUniqueResultException
     */
    public function applyCropPayloadToDefaultRoi(
        ImageFile $image,
        RequestedCropDto $cropPayload,
        bool $validate = true
    ): string {
        if ($image->getAssetAttributes()->getStatus()->isNot(AssetFileProcessStatus::Processed)) {
            throw new DomainException(sprintf('Image id (%s) is not processed', $image->getId()));
        }

        $roi = $this->regionOfInterestRepository->findByImageIdAndPosition((string) $image->getId(), App::ZERO);
        if (null === $roi) {
            throw new DomainException(sprintf('Image has no default ROI (%s)', $image->getId()));
        }

        return $this->applyCropPayload($image, $cropPayload, $roi, $validate);
    }

    /**
     * @throws InvalidCropException
     * @throws FilesystemException
     * @throws ImageManipulatorException
     */
    public function applyCropByTag(
        ImageFile $image,
        string $tag,
    ): string {
        $cropAllowItem = $this->configurationProvider->getFirstTaggedAllowItem($tag);

        if (null === $cropAllowItem) {
            throw new DomainException(sprintf('Tag (%s) not found', $tag));
        }

        return $this->applyCropPayloadToDefaultRoi(
            image: $image,
            cropPayload: (new RequestedCropDto())
                ->setRoi(0)
                ->setRequestWidth($cropAllowItem->getWidth())
                ->setRequestHeight($cropAllowItem->getHeight()),
            validate: false,
        );
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
        bool $validate = true
    ): string {
        if ($validate) {
            $this->validateCrop($image, $cropPayload);
        }
        $crop = $this->cropFactory->prepareImageCrop($roi, $cropPayload, $image);

        return $this->cropProcessor->applyCrop($image, $crop);
    }

    /**
     * @throws InvalidCropException
     */
    private function validateCrop(ImageFile $image, RequestedCropDto $cropDto): void
    {
        $allowList = $this->allowListConfiguration->getListByDomain($image->getExtSystem()->getSlug());

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
