<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

final class ExtSystemAssetExternalProviderConfiguration
{
    public const string PROVIDER_NAME_KEY = 'provider_name';
    public const string TITLE_KEY = 'title';
    public const string IMPORT_AUTHOR_ID = 'import_author_id';
    public const string LISTING_LIMIT_KEY = 'listing_limit';

    public function __construct(
        private readonly string $providerName,
        private readonly string $title,
        private readonly string $importAuthorId,
        private readonly int $listingLimit,
    ) {
    }

    public static function getFromArrayConfiguration(array $config): self
    {
        return new self(
            $config[self::PROVIDER_NAME_KEY] ?? '',
            $config[self::TITLE_KEY] ?? '',
            $config[self::IMPORT_AUTHOR_ID] ?? '',
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

    public function getImportAuthorId(): string
    {
        return $this->importAuthorId;
    }

    public function getListingLimit(): int
    {
        return $this->listingLimit;
    }
}
