<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image\Crop;

use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\RegionOfInterest;
use AnzuSystems\CoreDamBundle\Helper\ImageHelper;
use AnzuSystems\CoreDamBundle\Helper\RoiHelper;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\Crop\RequestedCropDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageCropDto;

final class CropFactory
{
    private const DEFAULT_CROP_QUALITY = 75;

    private int $originWidth;
    private int $originHeight;
    private int $roiPointX;
    private int $roiPointY;
    private int $roiWidth;
    private int $roiHeight;
    private int $roiHorizontalAxis;
    private int $roiVerticalAxis;
    private float $roiRatio;
    private int $requestWidth;
    private int $requestHeight;
    private int $requestQuality;
    private float $requestRatio;

    public function prepareImageCrop(RegionOfInterest $roi, RequestedCropDto $cropPayload, ImageFile $image): ImageCropDto
    {
        $this->loadAttributes($roi, $cropPayload, $image);

        if (true === $this->missingRequestedWidthOrHeight($cropPayload)) {
            return $this->createOriginCrop();
        }
        if (true === ImageHelper::isSameAspectRatio($this->requestRatio, $this->roiRatio)) {
            return $this->createSameCrop();
        }
        if (true === ImageHelper::isWiderAspectRatio($this->requestRatio, $this->roiRatio)) {
            return $this->createWiderCrop();
        }

        return $this->createHigherCrop();
    }

    private function missingRequestedWidthOrHeight(RequestedCropDto $cropPayload): bool
    {
        return 0 === $cropPayload->getRequestWidth() || 0 === $cropPayload->getRequestHeight();
    }

    private function createOriginCrop(): ImageCropDto
    {
        return $this->createImageCrop(0, 0, $this->originWidth, $this->originHeight);
    }

    private function loadAttributes(RegionOfInterest $roi, RequestedCropDto $cropPayload, ImageFile $image): void
    {
        $this->originWidth = $image->getImageAttributes()->getWidth();
        $this->originHeight = $image->getImageAttributes()->getHeight();
        $originRatio = ImageHelper::getAspectRatio($this->originWidth, $this->originHeight);
        $this->roiWidth = RoiHelper::getRoiWidth($roi, $this->originWidth);
        $this->roiHeight = RoiHelper::getRoiHeight($roi, $this->originHeight);
        $this->roiRatio = ImageHelper::getAspectRatio($this->roiWidth, $this->roiHeight);
        $this->roiHorizontalAxis = RoiHelper::getBeginningOfHorizontalAxis($roi, $this->roiWidth);
        $this->roiVerticalAxis = RoiHelper::getBeginningOfVerticalAxis($roi, $this->roiHeight);
        $this->requestWidth = $this->getRequestedWidth($cropPayload, $originRatio);
        $this->requestHeight = $this->getRequestedHeight($cropPayload, $originRatio);
        $this->requestRatio = $this->requestWidth / $this->requestHeight;
        $this->requestQuality = $cropPayload->getQuality() ?? self::DEFAULT_CROP_QUALITY;
        $this->roiPointX = $roi->getPointX();
        $this->roiPointY = $roi->getPointY();
    }

    private function createWiderCrop(): ImageCropDto
    {
        $cropWidth = (int) ($this->roiHeight * $this->requestRatio);
        $cropHeight = $this->roiHeight;
        $pointX = (int) ($this->roiHorizontalAxis - ($cropWidth / 2));
        $pointY = $this->roiPointY;

        if ($this->isWiderThanOriginal($cropWidth, $this->originWidth)) {
            $pointY = (int) ($this->roiVerticalAxis - ($this->originWidth / (2 * $this->requestRatio)));
            $cropWidth = $this->originWidth;
            $cropHeight = (int) ($cropWidth / $this->requestRatio);

            return $this->createImageCrop(0, $pointY, $cropWidth, $cropHeight);
        }

        if ($this->isCropOutsideFromLeft($cropWidth, $this->roiHorizontalAxis)) {
            return $this->createImageCrop(0, $pointY, $cropWidth, $cropHeight);
        }

        if ($this->isCropOutsideFromRight($cropWidth, $this->originWidth, $this->roiHorizontalAxis)) {
            $pointX = $this->originWidth - $cropWidth;

            return $this->createImageCrop($pointX, $pointY, $cropWidth, $cropHeight);
        }

        return $this->createImageCrop($pointX, $pointY, $cropWidth, $cropHeight);
    }

    private function createHigherCrop(): ImageCropDto
    {
        $cropWidth = $this->roiWidth;
        $cropHeight = (int) ($cropWidth / $this->requestRatio);
        $pointX = $this->roiPointX;
        $pointY = (int) ($this->roiPointY - (($cropHeight - $this->roiHeight) / 2));

        if ($this->isHigherThanOriginal($cropHeight, $this->originHeight)) {
            $cropHeight = $this->originHeight;
            $cropWidth = (int) ($this->originHeight * $this->requestRatio);
            $pointX = (int) ($this->roiHorizontalAxis - ($cropWidth / 2));

            return $this->createImageCrop($pointX, 0, $cropWidth, $cropHeight);
        }
        if ($this->isCropOutsideFromTop($cropHeight, $this->roiVerticalAxis)) {
            return $this->createImageCrop($pointX, 0, $cropWidth, $cropHeight);
        }

        if ($this->isCropOutsideFromBottom($cropHeight, $this->originHeight, $this->roiVerticalAxis)) {
            $pointY = $this->originHeight - $cropHeight;

            return $this->createImageCrop($pointX, $pointY, $cropWidth, $cropHeight);
        }

        return $this->createImageCrop($pointX, $pointY, $cropWidth, $cropHeight);
    }

    private function getRequestedWidth(RequestedCropDto $cropPayload, float $roiRatio): int
    {
        if (0 === $cropPayload->getRequestWidth()) {
            return (int) ($cropPayload->getRequestHeight() * $roiRatio);
        }

        return $cropPayload->getRequestWidth();
    }

    private function getRequestedHeight(RequestedCropDto $cropPayload, float $roiRatio): int
    {
        if (0 === $cropPayload->getRequestHeight()) {
            return (int) ($cropPayload->getRequestWidth() / $roiRatio);
        }

        return $cropPayload->getRequestHeight();
    }

    private function createSameCrop(): ImageCropDto
    {
        return $this->createImageCrop($this->roiPointX, $this->roiPointY, $this->roiWidth, $this->roiHeight);
    }

    private function createImageCrop(int $pointX, int $pointY, int $width, int $height): ImageCropDto
    {
        return new ImageCropDto($pointX, $pointY, $width, $height, $this->requestWidth, $this->requestHeight, $this->requestQuality);
    }

    private function isWiderThanOriginal(int $cropWidth, int $originWidth): bool
    {
        return $cropWidth > $originWidth;
    }

    private function isCropOutsideFromLeft(int $cropWidth, int $roiHorizontalAxis): bool
    {
        return $cropWidth / 2 > $roiHorizontalAxis;
    }

    private function isCropOutsideFromRight(int $cropWidth, int $originWidth, int $roiHorizontalAxis): bool
    {
        return $cropWidth / 2 > $originWidth - $roiHorizontalAxis;
    }

    private function isHigherThanOriginal(int $cropHeight, int $originHeight): bool
    {
        return $cropHeight > $originHeight;
    }

    private function isCropOutsideFromTop(int $cropHeight, int $verticalAxis): bool
    {
        return $cropHeight / 2 > $verticalAxis;
    }

    private function isCropOutsideFromBottom(int $cropHeight, int $originalHeight, int $verticalAxis): bool
    {
        return $cropHeight / 2 > $originalHeight - $verticalAxis;
    }
}
