<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

use AnzuSystems\CoreDamBundle\Model\Configuration\TextsWriter\TextsWriterConfiguration;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionRequirementStrategy;

final class ExtSystemAssetTypeDistributionRequirementConfiguration
{
    public const string BLOCKED_BY_KEY = 'blocked_by';
    public const string CATEGORY_SELECT_KEY = 'category_select';
    public const string STRATEGY_KEY = 'strategy';
    public const string TITLE_KEY = 'title';
    public const string DISTRIBUTION_SERVICE_ID_KEY = 'distribution_service_id';
    public const string REQUIRED_AUTH_KEY = 'required_auth';
    public const string DISTRIBUTION_METADATA_MAP = 'distribution_metadata_map';

    public function __construct(
        private readonly string $distributionServiceId,
        private readonly string $title,
        private readonly array $blockedBy,
        private readonly ExtSystemAssetTypeDistributionRequirementCategorySelectConfiguration $categorySelectConfiguration,
        private readonly DistributionRequirementStrategy $strategy,
        private readonly bool $requiredAuth,
        private readonly array $metadataMap,
    ) {
    }

    public static function getFromArrayConfiguration(array $config): self
    {
        return new self(
            $config[self::DISTRIBUTION_SERVICE_ID_KEY] ?? '',
            $config[self::TITLE_KEY] ?? '',
            $config[self::BLOCKED_BY_KEY] ?? [],
            ExtSystemAssetTypeDistributionRequirementCategorySelectConfiguration::getFromArrayConfiguration($config[self::CATEGORY_SELECT_KEY] ?? []),
            DistributionRequirementStrategy::from($config[self::STRATEGY_KEY]),
            $config[self::REQUIRED_AUTH_KEY] ?? false,
            array_map(
                fn (array $episodeMapConfig): TextsWriterConfiguration => TextsWriterConfiguration::getFromArrayConfiguration($episodeMapConfig),
                $config[self::DISTRIBUTION_METADATA_MAP] ?? []
            )
        );
    }

    /**
     * @return array<int, TextsWriterConfiguration>
     */
    public function getMetadataMap(): array
    {
        return $this->metadataMap;
    }

    public function getDistributionServiceId(): string
    {
        return $this->distributionServiceId;
    }

    public function getBlockedBy(): array
    {
        return $this->blockedBy;
    }

    public function getCategorySelectConfiguration(): ExtSystemAssetTypeDistributionRequirementCategorySelectConfiguration
    {
        return $this->categorySelectConfiguration;
    }

    public function getStrategy(): DistributionRequirementStrategy
    {
        return $this->strategy;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function isRequiredAuth(): bool
    {
        return $this->requiredAuth;
    }
}
