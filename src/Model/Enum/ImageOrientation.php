<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;

enum ImageOrientation: string implements EnumInterface
{
    use BaseEnumTrait;

    public const OPTIONS = [
        self::LANDSCAPE,
        self::PORTRAIT,
        self::SQUARE,
    ];

    public const LANDSCAPE = 'L';
    public const PORTRAIT = 'P';
    public const SQUARE = 'S';

    case Landscape = self::LANDSCAPE;
    case Portrait = self::PORTRAIT;
    case Square = self::SQUARE;

    public const Default = self::Square;

    public static function fromImage(ImageFile $image): self
    {
        return self::getOrientation(
            $image->getImageAttributes()->getWidth(),
            $image->getImageAttributes()->getHeight()
        );
    }

    public static function fromVideo(VideoFile $video): self
    {
        return self::getOrientation(
            $video->getAttributes()->getWidth(),
            $video->getAttributes()->getHeight(),
        );
    }

    private static function getOrientation(int $width, int $height): self
    {
        if ($width > $height) {
            return self::Landscape;
        }
        if ($width < $height) {
            return self::Portrait;
        }

        return self::Square;
    }
}
