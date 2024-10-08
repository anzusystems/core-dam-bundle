<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Doctrine\Type;

use AnzuSystems\CommonBundle\Doctrine\Type\AbstractValueObjectType;
use AnzuSystems\CoreDamBundle\Model\ValueObject\Color;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Throwable;

final class ColorType extends AbstractValueObjectType
{
    public const string NAME = 'ColorType';

    public function convertToPHPValue($value, AbstractPlatform $platform): ?Color
    {
        if (null === $value) {
            return null;
        }

        try {
            /**
             * @var string $r
             * @var string $g
             * @var string $b
             *
             * @psalm-suppress PossiblyUndefinedArrayOffset
             */
            [$r, $g, $b] = str_split(ltrim($value, '#'), 2);

            return new Color(hexdec($r), hexdec($g), hexdec($b));
        } catch (Throwable) {
            throw new ConversionException();
        }
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): string
    {
        if ($value instanceof Color) {
            $value = $value->toString();
        }

        return $value;
    }
}
