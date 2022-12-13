<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration\TextsWriter;

use AnzuSystems\CoreDamBundle\Exception\InvalidArgumentException;

final class TextsWriterConfiguration
{
    public const SOURCE_PROPERTY_PATH_KEY = 'source_property_path';
    public const DESTINATION_PROPERTY_PATH_KEY = 'destination_property_path';
    public const NORMALIZERS_KEY = 'normalizers';

    public const NORMALIZERS_TYPE_KEY = 'type';
    public const NORMALIZERS_OPTIONS_KEY = 'options';

    private string $sourcePropertyPath;
    private string $destinationPropertyPath;
    private array $normalizers;

    public static function getFromArrayConfiguration(array $config): self
    {
        return (new self())
            ->setSourcePropertyPath($config[self::SOURCE_PROPERTY_PATH_KEY] ?? '')
            ->setDestinationPropertyPath($config[self::DESTINATION_PROPERTY_PATH_KEY] ?? '')
            ->setNormalizers(
                array_map(
                    fn (array $normalizerConfig): object => self::createNormalizerFromConfig($normalizerConfig),
                    $config[self::NORMALIZERS_KEY] ?? []
                )
            )
        ;
    }

    public function getSourcePropertyPath(): string
    {
        return $this->sourcePropertyPath;
    }

    public function setSourcePropertyPath(string $sourcePropertyPath): self
    {
        $this->sourcePropertyPath = $sourcePropertyPath;

        return $this;
    }

    public function getDestinationPropertyPath(): string
    {
        return $this->destinationPropertyPath;
    }

    public function setDestinationPropertyPath(string $destinationPropertyPath): self
    {
        $this->destinationPropertyPath = $destinationPropertyPath;

        return $this;
    }

    public function getNormalizers(): array
    {
        return $this->normalizers;
    }

    public function setNormalizers(array $normalizers): self
    {
        $this->normalizers = $normalizers;

        return $this;
    }

    private static function createNormalizerFromConfig(array $config): object
    {
        $type = $config[self::NORMALIZERS_TYPE_KEY] ?? '';
        $options = $config[self::NORMALIZERS_OPTIONS_KEY] ?? [];

        if (StringNormalizerConfiguration::TYPE === $type) {
            return StringNormalizerConfiguration::getFromArrayConfiguration($options);
        }
        if (HtmlNormalizerConfiguration::TYPE === $type) {
            return HtmlNormalizerConfiguration::getFromArrayConfiguration($options);
        }

        throw new InvalidArgumentException(
            sprintf(
                'Invalid Normalizer configuration (%s)',
                $type
            )
        );
    }
}
