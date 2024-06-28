<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

use AnzuSystems\CoreDamBundle\Model\Configuration\TextsWriter\TextsWriterConfiguration;

class ExtSystemAssetTypeConfiguration
{
    public const string ENABLED_KEY = 'enabled';
    public const string STORAGE_NAME_KEY = 'storage_name';
    public const string TITLE_CONFIG_KEY = 'title';
    public const string CHUNK_STORAGE_NAME_KEY = 'chunk_storage_name';
    public const string MIME_TYPES = 'mime_types';
    public const string FILE_SLOTS_KEY = 'file_slots';
    public const string SIZE_LIMIT_KEY = 'size_limit';
    public const string CUSTOM_METADATA_PINNED_AMOUNT = 'custom_metadata_pinned_amount';
    public const string KEYWORDS_KEY = 'keywords';
    public const string AUTHORS_KEY = 'authors';
    public const string DISTRIBUTION_KEY = 'distribution';
    public const string ASSET_EXTERNAL_PROVIDERS_MAP_KEY = 'asset_external_providers_map';

    public function __construct(
        private readonly bool $enabled,
        private readonly string $storageName,
        private readonly array $titleConfig,
        private readonly string $chunkStorageName,
        private readonly array $mimeTypes,
        private readonly int $sizeLimit,
        private readonly int $customMetadataPinnedAmount,
        private readonly array $assetExternalProvidersMap,
        private readonly ExtSystemAssetTypeSlotsConfiguration $slots,
        private readonly ExtSystemAssetTypeExifMetadataConfiguration $keywords,
        private readonly ExtSystemAssetTypeExifMetadataConfiguration $authors,
        private readonly ExtSystemAssetTypeDistributionConfiguration $distribution,
    ) {
    }

    public static function getFromArrayConfiguration(array $config): static
    {
        return new static(
            $config[self::ENABLED_KEY] ?? false,
            $config[self::STORAGE_NAME_KEY] ?? '',
            $config[self::TITLE_CONFIG_KEY] ?? [],
            $config[self::CHUNK_STORAGE_NAME_KEY] ?? '',
            $config[self::MIME_TYPES] ?? [],
            $config[self::SIZE_LIMIT_KEY] ?? 0,
            $config[self::CUSTOM_METADATA_PINNED_AMOUNT] ?? 0,
            array_map(
                fn (array $episodeMapConfig): TextsWriterConfiguration => TextsWriterConfiguration::getFromArrayConfiguration($episodeMapConfig),
                $config[self::ASSET_EXTERNAL_PROVIDERS_MAP_KEY] ?? []
            ),
            ExtSystemAssetTypeSlotsConfiguration::getFromArrayConfiguration(
                $config[self::FILE_SLOTS_KEY] ?? []
            ),
            ExtSystemAssetTypeExifMetadataConfiguration::getFromArrayConfiguration(
                $config[self::KEYWORDS_KEY] ?? []
            ),
            ExtSystemAssetTypeExifMetadataConfiguration::getFromArrayConfiguration(
                $config[self::AUTHORS_KEY] ?? []
            ),
            ExtSystemAssetTypeDistributionConfiguration::getFromArrayConfiguration(
                $config[self::DISTRIBUTION_KEY] ?? []
            ),
        );
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getChunkStorageName(): string
    {
        return $this->chunkStorageName;
    }

    public function getSizeLimit(): int
    {
        return $this->sizeLimit;
    }

    public function getCustomMetadataPinnedAmount(): int
    {
        return $this->customMetadataPinnedAmount;
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    public function getMimeTypes(): array
    {
        return $this->mimeTypes;
    }

    public function getSlots(): ExtSystemAssetTypeSlotsConfiguration
    {
        return $this->slots;
    }

    public function getKeywords(): ExtSystemAssetTypeExifMetadataConfiguration
    {
        return $this->keywords;
    }

    public function getAuthors(): ExtSystemAssetTypeExifMetadataConfiguration
    {
        return $this->authors;
    }

    public function getDistribution(): ExtSystemAssetTypeDistributionConfiguration
    {
        return $this->distribution;
    }

    public function getTitleConfig(): array
    {
        return $this->titleConfig;
    }

    /**
     * @return array<int, TextsWriterConfiguration>
     */
    public function getAssetExternalProvidersMap(): array
    {
        return $this->assetExternalProvidersMap;
    }
}
