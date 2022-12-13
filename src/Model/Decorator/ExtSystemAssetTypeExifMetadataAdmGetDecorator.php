<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Decorator;

use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAssetTypeExifMetadataConfiguration;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class ExtSystemAssetTypeExifMetadataAdmGetDecorator
{
    private ExtSystemAssetTypeExifMetadataConfiguration $configuration;

    public static function getInstance(ExtSystemAssetTypeExifMetadataConfiguration $configuration): self
    {
        return (new self())
            ->setConfiguration($configuration);
    }

    public function setConfiguration(ExtSystemAssetTypeExifMetadataConfiguration $configuration): self
    {
        $this->configuration = $configuration;

        return $this;
    }

    #[Serialize]
    public function isEnabled(): bool
    {
        return $this->configuration->isEnabled();
    }

    #[Serialize]
    public function isRequired(): bool
    {
        return $this->configuration->isRequired();
    }

    #[Serialize]
    public function getAutocompleteFromMetadataTags(): array
    {
        return $this->configuration->getAutocompleteFromMetadataTags();
    }
}
