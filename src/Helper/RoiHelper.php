<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Helper;

use AnzuSystems\CoreDamBundle\Entity\RegionOfInterest;

final class RoiHelper
{
    public static function getRoiWidth(RegionOfInterest $roi, int $width): int
    {
        return self::roundUp(($width * $roi->getPercentageWidth()));
    }

    public static function getRoiHeight(RegionOfInterest $roi, int $height): int
    {
        return self::roundUp($height * $roi->getPercentageHeight());
    }

    public static function getBeginningOfHorizontalAxis(RegionOfInterest $roi, int $roiWidth): int
    {
        return (int) (($roiWidth / 2) + $roi->getPointX());
    }

    public static function getBeginningOfVerticalAxis(RegionOfInterest $roi, int $roiHeight): int
    {
        return (int) (($roiHeight / 2) + $roi->getPointY());
    }

    private static function roundUp(float $value): int
    {
        return 1 > $value
            ? 1
            : (int) $value;
    }
}
