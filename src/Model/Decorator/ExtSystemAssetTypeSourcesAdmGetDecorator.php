<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Decorator;

use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAssetTypeSourcesConfiguration;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class ExtSystemAssetTypeSourcesAdmGetDecorator
{
    private ExtSystemAssetTypeSourcesConfiguration $configuration;

    public static function getInstance(ExtSystemAssetTypeSourcesConfiguration $configuration): self
    {
        return (new self())
            ->setConfiguration($configuration);
    }

    public function setConfiguration(ExtSystemAssetTypeSourcesConfiguration $configuration): self
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
        return $this->configuration->isRequiredInAdm();
    }

    #[Serialize]
    public function getCreditFormat(): string
    {
        return $this->configuration->getCreditFormat();
    }

    #[Serialize]
    public function getCreditSeparator(): string
    {
        return $this->configuration->getCreditSeparator();
    }
}
