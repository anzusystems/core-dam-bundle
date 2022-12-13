<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

final class DistributionsConfiguration
{
    public const API_DOMAIN_KEY = 'api_domain';

    public function __construct(
        private readonly string $apiDomainKey,
        private readonly int $defaultExtSystemId,
        private readonly int $defaultAssetLicenceId,
        private readonly bool $allowSelectExtSystem,
        private readonly bool $allowSelectLicenceId,
        private readonly int $maxBulkItemCount,
        private readonly SettingsChunkConfiguration $imageChunkConfig,
        private readonly bool $aclCheckEnabled,
        private readonly string $adminAllowListName,
    ) {
    }

    public static function getFromArrayConfiguration(
        array $settings
    ): self {
        return new self(
            $settings[self::API_DOMAIN_KEY] ?? '',
        );
    }
}
