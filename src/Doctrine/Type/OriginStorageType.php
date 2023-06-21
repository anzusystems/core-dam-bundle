<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Doctrine\Type;

use AnzuSystems\CommonBundle\Doctrine\Type\AbstractValueObjectType;
use AnzuSystems\CoreDamBundle\Model\ValueObject\OriginStorage;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Throwable;

final class OriginStorageType extends AbstractValueObjectType
{
    public const NAME = 'OriginStorageType';

    public function convertToPHPValue($value, AbstractPlatform $platform): ?OriginStorage
    {
        if (null === $value) {
            return null;
        }

        try {
            /**
             * @var string $storageName
             * @var string $path
             *
             * @psalm-suppress PossiblyUndefinedArrayOffset
             */
            [$storageName, $path] = explode('|', $value, 2);

            return new OriginStorage($storageName, $path);
        } catch (Throwable) {
            throw ConversionException::conversionFailed($value, $this->getName());
        }
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value instanceof OriginStorage) {
            $value = $value->toString();
        }

        return $value;
    }
}
