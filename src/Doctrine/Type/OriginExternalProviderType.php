<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Doctrine\Type;

use AnzuSystems\CommonBundle\Doctrine\Type\AbstractValueObjectType;
use AnzuSystems\CoreDamBundle\Model\ValueObject\OriginExternalProvider;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Throwable;

final class OriginExternalProviderType extends AbstractValueObjectType
{
    public const string NAME = 'OriginExternalProviderType';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?OriginExternalProvider
    {
        if (null === $value) {
            return null;
        }

        try {
            /**
             * @var string $providerName
             * @var string $id
             *
             * @psalm-suppress PossiblyUndefinedArrayOffset
             */
            [$providerName, $id] = explode('|', $value, 2);

            return new OriginExternalProvider($providerName, $id);
        } catch (Throwable) {
            throw new ConversionException();
        }
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value instanceof OriginExternalProvider) {
            $value = $value->toString();
        }

        return $value;
    }
}
