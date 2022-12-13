<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

final class ExtSystemAssetExternalProviderConfiguration
{
    public const PROVIDER_NAME_KEY = 'provider_name';
    public const TITLE_KEY = 'title';
    public const LISTING_LIMIT_KEY = 'listing_limit';

    public function __construct(
        private readonly string $providerName,
        private readonly string $title,
        private readonly int $listingLimit,
    ) {
    }

    public static function getFromArrayConfiguration(array $config): self
    {
        return new self(
            $config[self::PROVIDER_NAME_KEY] ?? '',
            $config[self::TITLE_KEY] ?? '',
            $config[self::LISTING_LIMIT_KEY] ?? AssetExternalProviderConfiguration::DEFAULT_LISTING_LIMIT,
        );
    }

    public function getProviderName(): string
    {
        return $this->providerName;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getListingLimit(): int
    {
        return $this->listingLimit;
    }
}
