<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

/**
 * Type that maps a database BIGINT to a PHP int.
 */
class BigIntType extends Type
{
    public function getName(): string
    {
        return Types::BIGINT;
    }

    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getBigIntTypeDeclarationSQL($column);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform): ?int
    {
        return null === $value ? null : (int) $value;
    }
}
