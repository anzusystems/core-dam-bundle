<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Configuration;

use AnzuSystems\CoreDamBundle\AssetExternalProvider\AssetExternalProviderContainer;
use AnzuSystems\CoreDamBundle\Model\Configuration\AssetExternalProviderConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\DistributionServiceConfiguration;

final class AssetExternalProviderConfigurationProvider
{
    /**
     * @param array<int, DistributionServiceConfiguration> $assetExternalProvidersCache
     */
    public function __construct(
        private readonly AssetExternalProviderContainer $assetExternalProviderContainer,
        private array $assetExternalProvidersCache = [],
    ) {
    }

    /**
     * @return array<string, AssetExternalProviderConfiguration>
     */
    public function getAssetExternalProviders(): array
    {
        $providerNames = $this->assetExternalProviderContainer->allProviderNames();

        return array_map(
            fn (string $providerName) => $this->getAssetExternalProvider($providerName),
            array_combine(keys: $providerNames, values: $providerNames)
        );
    }

    public function getAssetExternalProvider(string $providerName): AssetExternalProviderConfiguration
    {
        return $this->assetExternalProvidersCache[$providerName] ??= $this->assetExternalProviderContainer
            ->get($providerName)
            ->getConfiguration();
    }
}
