<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration\TextsWriter;

final class TextsWriterNormalizerConfiguration
{
    public const PROPERTY_PATH_KEY = 'property_path';
    public const NORMALIZERS_KEY = 'normalizers';

    private string $propertyPath;
    private array $normalizers;

    public static function getFromArrayConfiguration(array $config): self
    {
        return (new self())
            ->setPropertyPath($config[self::PROPERTY_PATH_KEY] ?? '')
        ;
    }

    public function getPropertyPath(): string
    {
        return $this->propertyPath;
    }
}
