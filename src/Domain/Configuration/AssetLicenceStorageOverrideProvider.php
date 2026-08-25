<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Configuration;

final readonly class AssetLicenceStorageOverrideProvider
{
    /**
     * @param array<int, array{storage_name: string, crop_storage_name: string}> $assetLicenceStorageOverrides
     */
    public function __construct(
        private array $assetLicenceStorageOverrides,
    ) {
    }

    // config, not entity: immutable per licence, changed only via deploy
    public function getStorageName(int $licenceId): ?string
    {
        return $this->assetLicenceStorageOverrides[$licenceId]['storage_name'] ?? null;
    }

    public function getCropStorageName(int $licenceId): ?string
    {
        return $this->assetLicenceStorageOverrides[$licenceId]['crop_storage_name'] ?? null;
    }
}
