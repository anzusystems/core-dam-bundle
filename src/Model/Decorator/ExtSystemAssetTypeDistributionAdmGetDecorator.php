<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Decorator;

use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAssetTypeDistributionConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAssetTypeDistributionRequirementConfiguration;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class ExtSystemAssetTypeDistributionAdmGetDecorator
{
    private ExtSystemAssetTypeDistributionConfiguration $configuration;

    public static function getInstance(ExtSystemAssetTypeDistributionConfiguration $configuration): self
    {
        return (new self())
            ->setConfiguration($configuration);
    }

    public function setConfiguration(ExtSystemAssetTypeDistributionConfiguration $configuration): self
    {
        $this->configuration = $configuration;

        return $this;
    }

    #[Serialize]
    public function getDistributionServices(): array
    {
        return $this->configuration->getDistributionServices();
    }

    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    public function getDistributionRequirements(): array
    {
        return array_map(
            fn (ExtSystemAssetTypeDistributionRequirementConfiguration $configuration): ExtSystemAssetTypeDistributionRequirementsAdmGetDecorator => ExtSystemAssetTypeDistributionRequirementsAdmGetDecorator::getInstance($configuration),
            $this->configuration->getDistributionRequirements()
        );
    }
}
