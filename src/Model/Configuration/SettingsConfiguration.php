<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

use AnzuSystems\CoreDamBundle\Model\Enum\Language;
use AnzuSystems\CoreDamBundle\Model\Enum\UserAuthType;

final class SettingsConfiguration
{
    public const API_DOMAIN_KEY = 'api_domain';
    public const NOTIFICATIONS = 'notifications';
    public const DEFAULT_EXT_SYSTEM_ID_KEY = 'default_ext_system_id';
    public const YOUTUBE_API_KEY_KEY = 'youtube_api_key';
    public const DISTRIBUTION_AUTH_REDIRECT_URL_KEY = 'distribution_auth_redirect_url';
    public const DEFAULT_ASSET_LICENCE_ID_KEY = 'default_asset_licence_id';
    public const ALLOW_SELECT_EXT_SYSTEM_KEY_KEY = 'allow_select_ext_system';
    public const ALLOW_SELECT_LICENCE_ID_KEY = 'allow_select_licence';
    public const MAX_BULK_ITEM_COUNT_KEY = 'max_bulk_item_count';
    public const IMAGE_CHUNK_CONFIG_KEY = 'image_chunk_config';
    public const ACL_CHECK_ENABLED_KEY = 'acl_check_enabled';
    public const APP_REDIS_KEY = 'app_redis';
    public const CACHE_REDIS_KEY = 'cache_redis';
    public const USER_AUTH_TYPE_KEY = 'user_auth_type';
    public const ADMIN_ALLOW_LIST_NAME_KEY = 'admin_allow_list_name';
    public const ELASTIC_INDEX_PREFIX_KEY = 'elastic_index_prefix';
    public const ELASTIC_LANGUAGE_DICTIONARIES_KEY = 'elastic_language_dictionaries';
    public const LIMITED_ASSET_LICENCE_FILES_COUNT = 'limited_asset_licence_files_count';

    public function __construct(
        private readonly string $elasticIndexPrefix,
        private readonly NotificationsConfiguration $notificationsConfig,
        private readonly array $elasticLanguageDictionaries,
        private readonly string $apiDomainKey,
        private readonly string $youtubeApiKey,
        private readonly int $defaultExtSystemId,
        private readonly int $defaultAssetLicenceId,
        private readonly bool $allowSelectExtSystem,
        private readonly bool $allowSelectLicenceId,
        private readonly int $maxBulkItemCount,
        private readonly SettingsChunkConfiguration $imageChunkConfig,
        private readonly bool $aclCheckEnabled,
        private readonly UserAuthType $userAuthType,
        private readonly string $adminAllowListName,
        private readonly string $distributionAuthRedirectUrl,
        private readonly int $limitedAssetLicenceFilesCount,
    ) {
    }

    public static function getFromArrayConfiguration(
        array $settings
    ): self {
        return new self(
            $settings[self::ELASTIC_INDEX_PREFIX_KEY] ?? '',
            NotificationsConfiguration::getFromArrayConfiguration($settings[self::NOTIFICATIONS] ?? []),
            array_map(fn (string $language) => Language::from($language), $settings[self::ELASTIC_LANGUAGE_DICTIONARIES_KEY] ?? []),
            $settings[self::API_DOMAIN_KEY] ?? '',
            $settings[self::YOUTUBE_API_KEY_KEY] ?? '',
            $settings[self::DEFAULT_EXT_SYSTEM_ID_KEY] ?? 0,
            $settings[self::DEFAULT_ASSET_LICENCE_ID_KEY] ?? 0,
            $settings[self::ALLOW_SELECT_EXT_SYSTEM_KEY_KEY] ?? false,
            $settings[self::ALLOW_SELECT_LICENCE_ID_KEY] ?? false,
            $settings[self::MAX_BULK_ITEM_COUNT_KEY] ?? 0,
            SettingsChunkConfiguration::getFromArrayConfiguration($settings[self::IMAGE_CHUNK_CONFIG_KEY] ?? []),
            $settings[self::ACL_CHECK_ENABLED_KEY] ?? true,
            UserAuthType::tryFrom((string) $settings[self::USER_AUTH_TYPE_KEY]) ?? UserAuthType::Default,
            $settings[self::ADMIN_ALLOW_LIST_NAME_KEY] ?? '',
            $settings[self::DISTRIBUTION_AUTH_REDIRECT_URL_KEY] ?? '',
            $settings[self::LIMITED_ASSET_LICENCE_FILES_COUNT] ?? 0,
        );
    }

    public function getApiDomainKey(): string
    {
        return $this->apiDomainKey;
    }

    public function getNotificationsConfig(): NotificationsConfiguration
    {
        return $this->notificationsConfig;
    }

    public function getDefaultExtSystemId(): int
    {
        return $this->defaultExtSystemId;
    }

    public function getDefaultAssetLicenceId(): int
    {
        return $this->defaultAssetLicenceId;
    }

    public function isAllowSelectExtSystem(): bool
    {
        return $this->allowSelectExtSystem;
    }

    public function isAllowSelectLicenceId(): bool
    {
        return $this->allowSelectLicenceId;
    }

    public function getMaxBulkItemCount(): int
    {
        return $this->maxBulkItemCount;
    }

    public function getImageChunkConfig(): SettingsChunkConfiguration
    {
        return $this->imageChunkConfig;
    }

    public function isAclCheckEnabled(): bool
    {
        return $this->aclCheckEnabled;
    }

    public function getAdminAllowListName(): string
    {
        return $this->adminAllowListName;
    }

    public function getYoutubeApiKey(): string
    {
        return $this->youtubeApiKey;
    }

    public function getElasticIndexPrefix(): string
    {
        return $this->elasticIndexPrefix;
    }

    /**
     * @return list<Language>
     */
    public function getElasticLanguageDictionaries(): array
    {
        return $this->elasticLanguageDictionaries;
    }

    public function getDistributionAuthRedirectUrl(): string
    {
        return $this->distributionAuthRedirectUrl;
    }

    public function getUserAuthType(): UserAuthType
    {
        return $this->userAuthType;
    }

    public function getLimitedAssetLicenceFilesCount(): int
    {
        return $this->limitedAssetLicenceFilesCount;
    }
}
