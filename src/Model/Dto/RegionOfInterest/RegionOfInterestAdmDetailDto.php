<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\RegionOfInterest;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\RegionOfInterest;
use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\ImageLinksHandler;
use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\ImageLinksManyTagsHandler;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\Validator\Constraints as Assert;

#[AppAssert\RegionOfInterest]
final class RegionOfInterestAdmDetailDto extends RegionOfInterestAdmListDto
{
    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\GreaterThanOrEqual(value: 0, message: ValidationException::ERROR_FIELD_LENGTH_MIN)]
    private int $pointX;

    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\GreaterThanOrEqual(value: 0, message: ValidationException::ERROR_FIELD_LENGTH_MIN)]
    private int $pointY;

    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\GreaterThan(value: 0, message: ValidationException::ERROR_FIELD_LENGTH_MIN)]
    private float $percentageWidth;

    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\GreaterThan(value: 0, message: ValidationException::ERROR_FIELD_LENGTH_MIN)]
    private float $percentageHeight;

    public function __construct()
    {
        $this->setPointX(0);
        $this->setPointY(0);
        $this->setPercentageWidth(0.0);
        $this->setPercentageHeight(0.0);
        parent::__construct();
    }

    public static function getInstance(RegionOfInterest $regionOfInterest): static
    {
        return parent::getInstance($regionOfInterest)
            ->setPercentageWidth($regionOfInterest->getPercentageWidth())
            ->setPercentageHeight($regionOfInterest->getPercentageHeight())
            ->setPointX($regionOfInterest->getPointX())
            ->setPointY($regionOfInterest->getPointY());
    }

    public function getPointX(): int
    {
        return $this->pointX;
    }

    public function setPointX(int $pointX): self
    {
        $this->pointX = $pointX;

        return $this;
    }

    public function getPointY(): int
    {
        return $this->pointY;
    }

    public function setPointY(int $pointY): self
    {
        $this->pointY = $pointY;

        return $this;
    }

    public function getPercentageWidth(): float
    {
        return $this->percentageWidth;
    }

    public function setPercentageWidth(float $percentageWidth): self
    {
        $this->percentageWidth = $percentageWidth;

        return $this;
    }

    public function getPercentageHeight(): float
    {
        return $this->percentageHeight;
    }

    public function setPercentageHeight(float $percentageHeight): self
    {
        $this->percentageHeight = $percentageHeight;

        return $this;
    }

    #[Serialize(handler: ImageLinksManyTagsHandler::class, type: ImageLinksHandler::TAG_ROI_EXAMPLE)]
    public function getLinks(): ImageFile
    {
        return $this->image;
    }
}
