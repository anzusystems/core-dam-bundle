<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\RegionOfInterest;

use AnzuSystems\CoreDamBundle\Entity\RegionOfInterest;
use AnzuSystems\CoreDamBundle\Model\Dto\RegionOfInterest\RegionOfInterestAdmDetailDto;

final class RegionOfInterestFactory
{
    public function createRoi(RegionOfInterestAdmDetailDto $createDto): RegionOfInterest
    {
        return (new RegionOfInterest())
            ->setPercentageWidth($createDto->getPercentageWidth())
            ->setPercentageHeight($createDto->getPercentageHeight())
            ->setPointX($createDto->getPointX())
            ->setPointY($createDto->getPointY());
    }
}
