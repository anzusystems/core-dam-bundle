<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Decorator;

use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAssetTypeDistributionRequirementCategorySelectConfiguration;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class ExtSystemAssetTypeDistributionRequirementsCategorySelectAdmGetDecorator
{
    private ExtSystemAssetTypeDistributionRequirementCategorySelectConfiguration $configuration;

    public static function getInstance(ExtSystemAssetTypeDistributionRequirementCategorySelectConfiguration $configuration): self
    {
        return (new self())
            ->setConfiguration($configuration);
    }

    public function setConfiguration(ExtSystemAssetTypeDistributionRequirementCategorySelectConfiguration $configuration): self
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
}
