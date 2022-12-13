<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Helper;

use AnzuSystems\CommonBundle\Helper\MathHelper;
use AnzuSystems\CoreDamBundle\Entity\Embeds\ImageAttributes;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;

final class ImageHelper
{
    public static function isLandscape(ImageAttributes $imageAttributes): bool
    {
        return $imageAttributes->getWidth() > $imageAttributes->getHeight();
    }

    public static function getAspectRatio(int $width, int $height): float
    {
        return $width / $height;
    }

    public static function isWiderAspectRatio(float $sourceRatio, float $dstRatio): bool
    {
        return $sourceRatio > $dstRatio;
    }

    public static function isSameAspectRatio(float $sourceRatio, float $dstRatio): bool
    {
        return MathHelper::floatEquals($sourceRatio, $dstRatio);
    }

    public static function isMediumCrop(int $width, int $height): bool
    {
        return $width < ImageFile::MEDIUM_CROP_SIZE && $height < ImageFile::MEDIUM_CROP_SIZE;
    }

    public static function isSmallCrop(int $width, int $height): bool
    {
        return $width < ImageFile::SMALL_CROP_SIZE && $height < ImageFile::SMALL_CROP_SIZE;
    }

    public static function isPointInRight(int $pointX, int $width): bool
    {
        return $width / 2 < $pointX;
    }

    public static function isPointOnBottom(int $pointY, int $height): bool
    {
        return $height / 2 < $pointY;
    }

    public static function isLeftTop(int $pointX, int $pointY, int $width, int $height): bool
    {
        return false === self::isPointInRight($pointX, $width) && false === self::isPointOnBottom($pointY, $height);
    }

    public static function isRightTop(int $pointX, int $pointY, int $width, int $height): bool
    {
        return true === self::isPointInRight($pointX, $width) && false === self::isPointOnBottom($pointY, $height);
    }

    public static function isLeftBottom(int $pointX, int $pointY, int $width, int $height): bool
    {
        return false === self::isPointInRight($pointX, $width) && self::isPointOnBottom($pointY, $height);
    }

    public static function isRightBottom(int $pointX, int $pointY, int $width, int $height): bool
    {
        return self::isPointInRight($pointX, $width) && self::isPointOnBottom($pointY, $height);
    }
}
