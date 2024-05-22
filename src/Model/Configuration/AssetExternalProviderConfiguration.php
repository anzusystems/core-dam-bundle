<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

class AssetExternalProviderConfiguration
{
    public const int DEFAULT_LISTING_LIMIT = 30;

    public const string PROVIDER_KEY = 'provider';
    public const string LISTING_LIMIT_KEY = 'listing_limit';
    public const string TITLE_KEY = 'title';
    public const string OPTIONS_KEY = 'options';

    public function __construct(
        private readonly string $provider,
        private readonly string $title,
        private readonly int $listingLimit,
        private readonly array $options,
    ) {
    }

    public static function getFromArrayConfiguration(array $config): static
    {
        return new static(
            $config[self::PROVIDER_KEY] ?? '',
            $config[self::TITLE_KEY] ?? '',
            $config[self::OPTIONS_KEY][self::LISTING_LIMIT_KEY] ?? self::DEFAULT_LISTING_LIMIT,
            $config[self::OPTIONS_KEY] ?? [],
        );
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getListingLimit(): int
    {
        return $this->listingLimit;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
