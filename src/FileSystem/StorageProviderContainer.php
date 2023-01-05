<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\FileSystem;

use Symfony\Component\DependencyInjection\ServiceLocator;

final class StorageProviderContainer
{
    public function __construct(
        private readonly ServiceLocator $storagesContainer,
    ) {
    }

    public function get(string $providerName): AbstractFilesystem
    {
        return $this->storagesContainer->get($providerName);
    }

    public function has(string $providerName): bool
    {
        return $this->storagesContainer->has($providerName);
    }
}
