<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Decorator;

use AnzuSystems\CoreDamBundle\Model\Configuration\DistributionServiceConfiguration;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class DistributionServiceAdmGetDecorator
{
    private DistributionServiceConfiguration $configuration;

    public static function getInstance(DistributionServiceConfiguration $configuration): self
    {
        return (new self())
            ->setConfiguration($configuration);
    }

    public function getConfiguration(): DistributionServiceConfiguration
    {
        return $this->configuration;
    }

    public function setConfiguration(DistributionServiceConfiguration $configuration): self
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
    public function getAllowedRedistributeStatuses(): array
    {
        return $this->configuration->getAllowedRedistributeStatuses();
    }
}
