<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\AssetExternalProvider;

use AnzuSystems\CoreDamBundle\AssetExternalProvider\Provider\AssetExternalProviderInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class AssetExternalProviderContainer
{
    public function __construct(
        private readonly ServiceLocator $providersContainer,
    ) {
    }

    /**
     * @return list<string>
     */
    public function allProviderNames(): array
    {
        return array_keys($this->providersContainer->getProvidedServices());
    }

    public function get(string $providerName): AssetExternalProviderInterface
    {
        return $this->providersContainer->get($providerName);
    }

    public function has(string $providerName): bool
    {
        return $this->providersContainer->has($providerName);
    }
}
