<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Asset;

use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemInterface;
use AnzuSystems\CoreDamBundle\Model\Configuration\TextsWriter\TextsWriterConfiguration;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

final class AssetTextsWriter
{
    public function __construct(
        private readonly PropertyAccessorInterface $propertyAccessor,
        private readonly AssetTextStringNormalizer $textStringNormalizer,
    ) {
    }

    /**
     * @param array<string, TextsWriterConfiguration> $config
     */
    public function writeValues(object $from, ExtSystemInterface $to, array $config): void
    {
        foreach ($config as $propertyConfig) {
            $this->propertyAccessor->setValue(
                objectOrArray: $to,
                propertyPath: $propertyConfig->getDestinationPropertyPath(),
                value: $this->getValue($from, $propertyConfig)
            );
        }
    }

    private function getValue(object $from, TextsWriterConfiguration $configuration): string
    {
        $value = $this->propertyAccessor->getValue(
            objectOrArray: $from,
            propertyPath: $configuration->getSourcePropertyPath()
        );

        if (is_string($value) && false === empty($configuration->getNormalizers())) {
            $value = $this->textStringNormalizer->normalizeAll($value, $configuration->getNormalizers());
        }

        return $value;
    }
}
