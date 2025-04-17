<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Decorator;

use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAssetTypeConfiguration;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

class ExtSystemAssetTypeAdmGetDecorator
{
    protected ExtSystemAssetTypeConfiguration $configuration;

    public static function getInstance(ExtSystemAssetTypeConfiguration $configuration): static
    {
        return (new static())
            ->setConfiguration($configuration);
    }

    public function setConfiguration(ExtSystemAssetTypeConfiguration $configuration): static
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
    public function getDefaultSlotName(): string
    {
        return $this->configuration->getSlots()->getDefault();
    }

    #[Serialize]
    public function getSizeLimit(): int
    {
        return $this->configuration->getSizeLimit();
    }

    #[Serialize]
    public function getCustomMetadataPinnedAmount(): int
    {
        return $this->configuration->getCustomMetadataPinnedAmount();
    }

    #[Serialize]
    public function getSlots(): array
    {
        return $this->configuration->getSlots()->getSlots();
    }

    #[Serialize]
    public function getMimeTypes(): array
    {
        return $this->configuration->getMimeTypes();
    }

    #[Serialize]
    public function getKeywords(): ExtSystemAssetTypeExifMetadataAdmGetDecorator
    {
        return ExtSystemAssetTypeExifMetadataAdmGetDecorator::getInstance($this->configuration->getKeywords());
    }

    #[Serialize]
    public function getAuthors(): ExtSystemAssetTypeExifMetadataAdmGetDecorator
    {
        return ExtSystemAssetTypeExifMetadataAdmGetDecorator::getInstance($this->configuration->getAuthors());
    }

    #[Serialize]
    public function getDistribution(): ExtSystemAssetTypeDistributionAdmGetDecorator
    {
        return ExtSystemAssetTypeDistributionAdmGetDecorator::getInstance(
            $this->configuration->getDistribution()
        );
    }
}
