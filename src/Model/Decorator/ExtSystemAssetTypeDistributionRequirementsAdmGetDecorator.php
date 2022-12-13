<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Decorator;

use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAssetTypeDistributionRequirementConfiguration;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionRequirementStrategy;
use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\DistributionHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class ExtSystemAssetTypeDistributionRequirementsAdmGetDecorator
{
    private ExtSystemAssetTypeDistributionRequirementConfiguration $configuration;

    public static function getInstance(ExtSystemAssetTypeDistributionRequirementConfiguration $configuration): self
    {
        return (new self())
            ->setConfiguration($configuration);
    }

    public function setConfiguration(ExtSystemAssetTypeDistributionRequirementConfiguration $configuration): self
    {
        $this->configuration = $configuration;

        return $this;
    }

    #[Serialize(handler: DistributionHandler::class)]
    public function getDistributionService(): string
    {
        return $this->configuration->getDistributionServiceId();
    }

    #[Serialize]
    public function getBlockedBy(): array
    {
        return $this->configuration->getBlockedBy();
    }

    #[Serialize]
    public function isRequiredAuth(): bool
    {
        return $this->configuration->isRequiredAuth();
    }

    #[Serialize]
    public function getTitle(): string
    {
        return $this->configuration->getTitle();
    }

    #[Serialize]
    public function getCategorySelect(): ExtSystemAssetTypeDistributionRequirementsCategorySelectAdmGetDecorator
    {
        return ExtSystemAssetTypeDistributionRequirementsCategorySelectAdmGetDecorator::getInstance(
            $this->configuration->getCategorySelectConfiguration()
        );
    }

    #[Serialize]
    public function getStrategy(): DistributionRequirementStrategy
    {
        return $this->configuration->getStrategy();
    }
}
