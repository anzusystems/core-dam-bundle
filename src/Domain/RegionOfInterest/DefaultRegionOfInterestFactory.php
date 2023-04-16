<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\RegionOfInterest;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\RegionOfInterest;
use AnzuSystems\CoreDamBundle\Helper\ImageHelper;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemImageTypeConfiguration;
use InvalidArgumentException;

final class DefaultRegionOfInterestFactory
{
    private const ROI_PERCENTAGE_DECIMAL_PLACES = 4;
    private const DEFAULT_ROI_TITLE = 'Default';

    public function __construct(
        private readonly ExtSystemConfigurationProvider $configurationProvider
    ) {
    }

    public function prepareDefaultRoi(ImageFile $image): RegionOfInterest
    {
        $roi = (new RegionOfInterest());
        $roi->setPosition(RegionOfInterest::FIRST_ROI_POSITION);
        $roi->setTitle(self::DEFAULT_ROI_TITLE);

        return $this->recalculateRoi($image, $roi);
    }

    public function recalculateRoi(ImageFile $image, RegionOfInterest $roi): RegionOfInterest
    {
        $configuration = $this->configurationProvider->getExtSystemConfigurationByAssetFile($image);
        if (false === ($configuration instanceof ExtSystemImageTypeConfiguration)) {
            throw new InvalidArgumentException('Asset type must be a type of image');
        }

        $imageAttributes = $image->getImageAttributes();
        $defaultRoiRatio = ImageHelper::getAspectRatio(
            $configuration->getRoiWidth(),
            $configuration->getRoiHeight(),
        );
        $originRatio = ImageHelper::getAspectRatio($imageAttributes->getRatioWidth(), $imageAttributes->getRatioHeight());

        if (ImageHelper::isLandscape($imageAttributes)) {
            if (ImageHelper::isWiderAspectRatio($originRatio, $defaultRoiRatio)) {
                $roiWidth = (int) ($imageAttributes->getHeight() * $defaultRoiRatio);
                $roiX = (int) (($imageAttributes->getWidth() - $roiWidth) / 2);

                return $this->fillRoiAttributes($roi, $roiX, 0, $roiWidth / $imageAttributes->getWidth(), 1);
            }

            $roiY = (int) (($imageAttributes->getHeight() - ($imageAttributes->getWidth() / $defaultRoiRatio)) / 2);
            $roiHeightPercentage = (float) ($imageAttributes->getWidth() / $defaultRoiRatio / $imageAttributes->getHeight());

            return $this->fillRoiAttributes($roi, 0, $roiY, 1, $roiHeightPercentage);
        }

        if (ImageHelper::isSameAspectRatio($originRatio, $defaultRoiRatio)) {
            return $this->fillRoiAttributes($roi, 0, 0, 1, 1);
        }

        $roiY = (int) ($imageAttributes->getHeight() / 10);
        $roiHeightPercentage = (float) ($imageAttributes->getWidth() / $defaultRoiRatio / $imageAttributes->getHeight());

        return $this->fillRoiAttributes($roi, 0, $roiY, 1, $roiHeightPercentage);
    }

    private function fillRoiAttributes(RegionOfInterest $roi, int $pointX, int $pointY, float $widthPercentage, float $heightPercentage): RegionOfInterest
    {
        $widthPercentage = min($widthPercentage, 1.0);
        $heightPercentage = min($heightPercentage, 1.0);

        return $roi
            ->setPointX($pointX)
            ->setPointY($pointY)
            ->setPercentageWidth(floor(($widthPercentage * 100)) / 100)
            ->setPercentageHeight(floor(($heightPercentage * 100)) / 100);
    }
}
