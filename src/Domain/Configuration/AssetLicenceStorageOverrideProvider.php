<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Configuration;

use AnzuSystems\CoreDamBundle\Model\Configuration\AssetLicenceStorageOverrideConfiguration;

final readonly class AssetLicenceStorageOverrideProvider
{
    /**
     * @var array<int, AssetLicenceStorageOverrideConfiguration>
     */
    private array $overrides;

    /**
     * @param array<int, array<string, string>> $assetLicenceStorageOverrides raw config, typed at this boundary only
     */
    public function __construct(array $assetLicenceStorageOverrides)
    {
        $this->overrides = array_map(
            static fn (array $config): AssetLicenceStorageOverrideConfiguration => AssetLicenceStorageOverrideConfiguration::getFromArrayConfiguration($config),
            $assetLicenceStorageOverrides,
        );
    }

    // config, not entity: immutable per licence, changed only via deploy
    public function getStorageName(int $licenceId): ?string
    {
        return $this->getOverride($licenceId)?->getStorageName();
    }

    public function getCropStorageName(int $licenceId): ?string
    {
        return $this->getOverride($licenceId)?->getCropStorageName();
    }

    private function getOverride(int $licenceId): ?AssetLicenceStorageOverrideConfiguration
    {
        return $this->overrides[$licenceId] ?? null;
    }
}
