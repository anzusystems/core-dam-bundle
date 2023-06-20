<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Asset;

use AnzuSystems\CoreDamBundle\Model\Configuration\TextsWriter\TextsWriterConfiguration;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

final readonly class AssetTextsWriter
{
    public function __construct(
        private PropertyAccessorInterface $propertyAccessor,
        private AssetTextStringNormalizer $textStringNormalizer,
    ) {
    }

    /**
     * @param array<int, TextsWriterConfiguration> $config
     */
    public function writeValues(object $from, object $to, array $config, bool $reversedConfig = false): void
    {
        foreach ($config as $propertyConfig) {
            $this->propertyAccessor->setValue(
                objectOrArray: $to,
                propertyPath: $reversedConfig
                    ? $propertyConfig->getSourcePropertyPath()
                    : $propertyConfig->getDestinationPropertyPath(),
                value: $this->getValue($from, $propertyConfig, $reversedConfig)
            );
        }
    }

    /**
     * @param array<int, TextsWriterConfiguration> $config
     */
    public function getFirstValue(object $from, array $config): mixed
    {
        foreach ($config as $propertyConfig) {
            $value = $this->getValue($from, $propertyConfig);
            if (false === empty($value)) {
                return $value;
            }
        }

        return null;
    }

    private function getValue(object $from, TextsWriterConfiguration $configuration, bool $reversed = false): mixed
    {
        $value = $this->propertyAccessor->getValue(
            objectOrArray: $from,
            propertyPath: $reversed
                ? $configuration->getDestinationPropertyPath()
                : $configuration->getSourcePropertyPath(),
        );

        if ((null === $value || is_string($value)) && false === empty($configuration->getNormalizers())) {
            $value = $this->textStringNormalizer->normalizeAll((string) $value, $configuration->getNormalizers());
        }

        return $value;
    }
}
