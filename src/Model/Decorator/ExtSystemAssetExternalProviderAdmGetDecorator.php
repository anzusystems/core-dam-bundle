<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Decorator;

use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAssetExternalProviderConfiguration;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class ExtSystemAssetExternalProviderAdmGetDecorator
{
    private ExtSystemAssetExternalProviderConfiguration $configuration;

    public static function getInstance(ExtSystemAssetExternalProviderConfiguration $configuration): self
    {
        return (new self())
            ->setConfiguration($configuration);
    }

    public function setConfiguration(ExtSystemAssetExternalProviderConfiguration $configuration): self
    {
        $this->configuration = $configuration;

        return $this;
    }

    #[Serialize]
    public function getTitle(): string
    {
        return $this->configuration->getTitle();
    }

    #[Serialize]
    public function getListingLimit(): int
    {
        return $this->configuration->getListingLimit();
    }
}
