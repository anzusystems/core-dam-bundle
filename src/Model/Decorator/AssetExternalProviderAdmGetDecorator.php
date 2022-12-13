<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Decorator;

use AnzuSystems\CoreDamBundle\Model\Configuration\AssetExternalProviderConfiguration;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class AssetExternalProviderAdmGetDecorator
{
    private AssetExternalProviderConfiguration $configuration;

    public static function getInstance(AssetExternalProviderConfiguration $configuration): self
    {
        return (new self())
            ->setConfiguration($configuration);
    }

    public function getConfiguration(): AssetExternalProviderConfiguration
    {
        return $this->configuration;
    }

    public function setConfiguration(AssetExternalProviderConfiguration $configuration): self
    {
        $this->configuration = $configuration;

        return $this;
    }

    #[Serialize]
    public function getTitle(): string
    {
        return $this->configuration->getTitle();
    }
}
