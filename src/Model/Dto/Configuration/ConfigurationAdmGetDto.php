<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Configuration;

use AnzuSystems\CoreDamBundle\Model\Configuration\AssetExternalProviderConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\DistributionServiceConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\SettingsConfiguration;
use AnzuSystems\CoreDamBundle\Model\Decorator\AssetExternalProviderAdmGetDecorator;
use AnzuSystems\CoreDamBundle\Model\Decorator\DistributionServiceAdmGetDecorator;
use AnzuSystems\CoreDamBundle\Model\Decorator\SettingsConfigurationAdmGetDecorator;
use AnzuSystems\CoreDamBundle\Model\ValueObject\Color;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class ConfigurationAdmGetDto
{
    private SettingsConfiguration $decoratedSettings;
    private array $colorSet;
    private array $assetExternalProviders;
    private array $distributionServices;

    /**
     * @param array<string, AssetExternalProviderConfiguration> $assetExternalProviders
     * @param array<string, DistributionServiceConfiguration> $distributionServices
     */
    public static function getInstance(
        SettingsConfiguration $decoratedSettings,
        array $colorSet,
        array $assetExternalProviders,
        array $distributionServices,
    ): self {
        return (new self())
            ->setDecoratedSettings($decoratedSettings)
            ->setColorSet($colorSet)
            ->setAssetExternalProviders($assetExternalProviders)
            ->setDistributionServices($distributionServices)
        ;
    }

    public function getDecoratedSettings(): SettingsConfiguration
    {
        return $this->decoratedSettings;
    }

    public function setDecoratedSettings(SettingsConfiguration $decoratedSettings): self
    {
        $this->decoratedSettings = $decoratedSettings;

        return $this;
    }

    /**
     * @return array<string, AssetExternalProviderAdmGetDecorator>
     */
    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    public function getAssetExternalProviders(): array
    {
        return array_map(
            static fn (AssetExternalProviderConfiguration $configuration) => AssetExternalProviderAdmGetDecorator::getInstance($configuration),
            $this->assetExternalProviders,
        );
    }

    public function setAssetExternalProviders(array $assetExternalProviders): self
    {
        $this->assetExternalProviders = $assetExternalProviders;

        return $this;
    }

    /**
     * @return array<string, DistributionServiceAdmGetDecorator>
     */
    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    public function getDistributionServices(): array
    {
        return array_map(
            static fn (DistributionServiceConfiguration $configuration) => DistributionServiceAdmGetDecorator::getInstance($configuration),
            $this->distributionServices,
        );
    }

    public function setDistributionServices(array $distributionServices): self
    {
        $this->distributionServices = $distributionServices;

        return $this;
    }

    public function setColorSet(array $colorSet): self
    {
        $this->colorSet = $colorSet;

        return $this;
    }

    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    public function getColorSet(): array
    {
        return array_map(
            fn (array $rgb): string => (new Color(...$rgb))->toString(),
            $this->colorSet
        );
    }

    #[Serialize]
    public function getSettings(): SettingsConfigurationAdmGetDecorator
    {
        return SettingsConfigurationAdmGetDecorator::getInstance($this->decoratedSettings);
    }
}
