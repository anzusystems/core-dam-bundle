<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DependencyInjection;

use AnzuSystems\CoreDamBundle\Domain\Configuration\AllowListConfiguration;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Configuration\AssetExternalProviderConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\CacheConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\CropAllowListConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\DistributionServiceConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAssetExternalProviderConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAssetTypeConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAssetTypeDistributionConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAssetTypeDistributionRequirementConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAssetTypeExifMetadataConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAudioTypeConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemDocumentTypeConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemImageTypeConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemVideoTypeConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\NotificationsConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\SettingsChunkConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\SettingsConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\TextsWriter\TextsWriterConfiguration;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionStrategy;
use AnzuSystems\CoreDamBundle\Model\Enum\Language;
use AnzuSystems\CoreDamBundle\Model\Enum\UserAuthType;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('anzu_systems_core_dam');

        $treeBuilder->getRootNode()
            ->children()
            ->append($this->addSettingsSection())
            ->append($this->addDistributionsSection())
            ->append($this->addDistributionsSection())
            ->append($this->addAssetExternalProvidersSection())
            ->append($this->addDisplayTitleSection())
            ->append($this->addExtSystemSection())
            ->append($this->addFileOperationsSection())
            ->append($this->addImageSection())
            ->append($this->addDomainsSection())
            ->append($this->addExifMetadataSection())
            ->append($this->addStoragesSection())
            ->end()
        ;

        return $treeBuilder;
    }

    public static function addTextMapperConfiguration(string $name): NodeDefinition
    {
        return (new TreeBuilder($name))->getRootNode()
            ->useAttributeAsKey('name')
            ->arrayPrototype()
                ->children()
                    ->scalarNode(TextsWriterConfiguration::SOURCE_PROPERTY_PATH_KEY)
                        ->isRequired()
                    ->end()
                    ->scalarNode(TextsWriterConfiguration::DESTINATION_PROPERTY_PATH_KEY)
                        ->defaultValue('')
                    ->end()
                    ->arrayNode(TextsWriterConfiguration::NORMALIZERS_KEY)
                        ->arrayPrototype()
                            ->children()
                                ->scalarNode(TextsWriterConfiguration::NORMALIZERS_TYPE_KEY)
                                    ->isRequired()
                                ->end()
                                ->arrayNode(TextsWriterConfiguration::NORMALIZERS_OPTIONS_KEY)
                                    ->variablePrototype()
                                        ->end()
                                    ->defaultValue([])
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function addDistributionsSection(): NodeDefinition
    {
        return (new TreeBuilder('distribution_services'))->getRootNode()
                ->useAttributeAsKey('name')
                ->arrayPrototype()
                    ->performNoDeepMerging()
                    ->children()
                        ->scalarNode(DistributionServiceConfiguration::TYPE_KEY)->isRequired()->end()
                        ->scalarNode(DistributionServiceConfiguration::TITLE_KEY)->isRequired()->end()
                        ->scalarNode(DistributionServiceConfiguration::MODULE_KEY)->isRequired()->end()
                        ->scalarNode(DistributionServiceConfiguration::ICON_PATH)->defaultValue('')->end()
                        ->arrayNode(DistributionServiceConfiguration::ALLOWED_REDISTRIBUTE_STATUSES)
                            ->defaultValue([DistributionProcessStatus::Failed->toString()])
                            ->scalarPrototype()->end()
                            ->validate()
                            ->ifTrue(function (array $values): bool {
                                foreach ($values as $value) {
                                    if (false === in_array($value, DistributionProcessStatus::values(), true)) {
                                        return true;
                                    }
                                }

                                return false;
                            })
                            ->thenInvalid('Only values (' . implode(',', DistributionProcessStatus::values()) . ') allowed')
                            ->end()
                        ->end()
                        ->booleanNode(DistributionServiceConfiguration::REQUIRED_AUTH_KEY)
                            ->defaultValue(false)
                        ->end()
                        ->scalarNode(DistributionServiceConfiguration::AUTH_REDIRECT_URL_KEY)->defaultValue(null)->end()

                        ->booleanNode(DistributionServiceConfiguration::USE_MOCK_KEY)
                            ->defaultValue(false)
                        ->end()
                        ->arrayNode(DistributionServiceConfiguration::MOCK_OPTIONS_KEY)
                            ->variablePrototype()
                            ->end()
                            ->defaultValue([])
                        ->end()
                        ->arrayNode(DistributionServiceConfiguration::OPTIONS_KEY)
                            ->variablePrototype()
                            ->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                ->end();
    }

    private function addAssetExternalProvidersSection(): NodeDefinition
    {
        return (new TreeBuilder('asset_external_providers'))->getRootNode()
            ->useAttributeAsKey('name')
            ->arrayPrototype()
                ->performNoDeepMerging()
                ->children()
                    ->scalarNode(AssetExternalProviderConfiguration::TITLE_KEY)->isRequired()->end()
                    ->scalarNode(AssetExternalProviderConfiguration::PROVIDER_KEY)->isRequired()->end()
                    ->arrayNode(AssetExternalProviderConfiguration::OPTIONS_KEY)
                        ->variablePrototype()
                        ->end()
                        ->defaultValue([])
                    ->end()
                ->end()
            ->end();
    }

    private function addDisplayTitleSection(): NodeDefinition
    {
        return (new TreeBuilder('display_title'))->getRootNode()
            ->append($this::addTextMapperConfiguration(AssetType::Image->toString()))
            ->append($this::addTextMapperConfiguration(AssetType::Audio->toString()))
            ->append($this::addTextMapperConfiguration(AssetType::Document->toString()))
            ->append($this::addTextMapperConfiguration(AssetType::Video->toString()))
        ;
    }

    private function addSettingsSection(): NodeDefinition
    {
        return (new TreeBuilder('settings'))->getRootNode()
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode(SettingsConfiguration::USER_ENTITY_CLASS)->defaultValue('App\\Entity\\User')->end()
                ->scalarNode(SettingsConfiguration::API_DOMAIN_KEY)
                    ->isRequired()
                ->end()
                ->scalarNode(SettingsConfiguration::REDIRECT_DOMAIN_KEY)
                    ->isRequired()
                ->end()
                ->arrayNode(SettingsConfiguration::NOTIFICATIONS)
                    ->canBeDisabled()
                    ->children()
                        ->scalarNode(NotificationsConfiguration::TOPIC)->end()
                        ->arrayNode(NotificationsConfiguration::GPS_CONFIG)
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode(SettingsConfiguration::UNSPLASH_API_CLIENT)
                    ->defaultValue('https://api.unsplash.com')
                    ->isRequired()
                ->end()
                ->scalarNode(SettingsConfiguration::JW_PLAYER_API_CLIENT)
                    ->defaultValue('https://api.jwplayer.com')
                    ->isRequired()
                ->end()
                ->scalarNode(SettingsConfiguration::JW_PLAYER_CDN_API_CLIENT)
                    ->defaultValue('https://cdn.jwplayer.com')
                    ->isRequired()
                ->end()
                ->scalarNode(SettingsConfiguration::ELASTIC_INDEX_PREFIX_KEY)
                    ->isRequired()
                ->end()
                ->arrayNode(SettingsConfiguration::ELASTIC_LANGUAGE_DICTIONARIES_KEY)
                    ->defaultValue([])
                        ->scalarPrototype()->end()
                    ->validate()
                        ->ifTrue(function (array $values): bool {
                            foreach ($values as $value) {
                                if (false === in_array($value, Language::values(), true)) {
                                    return true;
                                }
                            }

                            return false;
                        })
                        ->thenInvalid('Only values (' . implode(',', Language::values()) . ') allowed')
                    ->end()
                ->end()
                ->scalarNode(SettingsConfiguration::YOUTUBE_API_KEY_KEY)
                    ->defaultValue('')
                ->end()
                ->scalarNode(SettingsConfiguration::DISTRIBUTION_AUTH_REDIRECT_URL_KEY)
                    ->isRequired()
                ->end()
                ->integerNode(SettingsConfiguration::DEFAULT_EXT_SYSTEM_ID_KEY)
                    ->info('Default ExtSystem:id')
                    ->isRequired()
                ->end()
                ->integerNode(SettingsConfiguration::DEFAULT_ASSET_LICENCE_ID_KEY)
                    ->info('Default AssetLicence:id which belongs to specified ExtSystem')
                    ->isRequired()
                ->end()
                ->booleanNode(SettingsConfiguration::ALLOW_SELECT_EXT_SYSTEM_KEY_KEY)
                    ->defaultFalse()
                ->end()
                ->booleanNode(SettingsConfiguration::ALLOW_SELECT_LICENCE_ID_KEY)
                    ->defaultFalse()
                ->end()
                ->scalarNode(SettingsConfiguration::ADMIN_ALLOW_LIST_NAME_KEY)
                    ->isRequired()
                ->end()
                ->scalarNode(SettingsConfiguration::MAX_BULK_ITEM_COUNT_KEY)
                    ->isRequired()
                ->end()
                ->booleanNode(SettingsConfiguration::ACL_CHECK_ENABLED_KEY)
                    ->defaultTrue()
                ->end()
                ->scalarNode(SettingsConfiguration::APP_REDIS_KEY)
                    ->isRequired()
                ->end()
                ->scalarNode(SettingsConfiguration::CACHE_REDIS_KEY)
                    ->isRequired()
                ->end()
                ->scalarNode(SettingsConfiguration::NOT_FOUND_IMAGE_ID)
                    ->isRequired()
                ->end()
                ->enumNode(SettingsConfiguration::USER_AUTH_TYPE_KEY)
                    ->values(UserAuthType::values())
                    ->defaultValue(UserAuthType::Default->toString())
                ->end()
                ->integerNode(SettingsConfiguration::LIMITED_ASSET_LICENCE_FILES_COUNT)
                    ->info('Number of allowed files for an asset licence with enabled limitation.')
                    ->defaultValue(100)
                    ->isRequired()
                ->end()
                ->append($this->addChunkConfiguration())
            ->end();
    }

    private function addChunkConfiguration(): NodeDefinition
    {
        return (new TreeBuilder(SettingsConfiguration::IMAGE_CHUNK_CONFIG_KEY))->getRootNode()
            ->addDefaultsIfNotSet()
            ->children()
                ->integerNode(SettingsChunkConfiguration::MIN_SIZE_KEY)
                    ->info('Minimal allowed chunk size')
                    ->isRequired()
                ->end()
                ->integerNode(SettingsChunkConfiguration::MAX_SIZE_KEY)
                    ->info('Maximum allowed chunk size')
                    ->isRequired()
                ->end()
            ->end();
    }

    private function addStoragesSection(): NodeDefinition
    {
        return (new TreeBuilder('storages'))->getRootNode()
            ->useAttributeAsKey('name')
            ->arrayPrototype()
                ->performNoDeepMerging()
                ->children()
                    ->scalarNode('adapter')->isRequired()->end()
                    ->booleanNode('fallback_enabled')->defaultFalse()->end()
                    ->scalarNode('fallback_storage')->defaultValue('')->end()
                    ->arrayNode('options')
                        ->variablePrototype()
                        ->end()
                        ->defaultValue([])
                    ->end()
                ->end()
            ->end();
    }

    private function addExtSystemSection(): NodeDefinition
    {
        return (new TreeBuilder('ext_systems'))->getRootNode()
            ->useAttributeAsKey('name')
            ->arrayPrototype()
                ->children()
                    ->scalarNode('id')->end()
                    ->scalarNode(ExtSystemConfiguration::EXT_STORAGE_KEY)->end()
                    ->append($this->addExtSystemAssetExternalProvidersSection())
                    ->append($this->addFileExtSystemSection(AssetType::Image))
                    ->append($this->addFileExtSystemSection(AssetType::Audio))
                    ->append($this->addFileExtSystemSection(AssetType::Document))
                    ->append($this->addFileExtSystemSection(AssetType::Video))
                ->end()
            ->end();
    }

    private function addExtSystemAssetExternalProvidersSection(): NodeDefinition
    {
        return (new TreeBuilder(ExtSystemConfiguration::ASSET_EXTERNAL_PROVIDERS_KEY))->getRootNode()
            ->useAttributeAsKey('name')
            ->arrayPrototype()
                ->children()
                    ->scalarNode(ExtSystemAssetExternalProviderConfiguration::TITLE_KEY)->end()
                    ->scalarNode(ExtSystemAssetExternalProviderConfiguration::IMPORT_AUTHOR_ID)->end()
                ->end()
            ->end();
    }

    private function addDistributionAssetTypeSection(): NodeDefinition
    {
        return (new TreeBuilder(ExtSystemAssetTypeConfiguration::DISTRIBUTION_KEY))->getRootNode()
            ->children()
                ->arrayNode(ExtSystemAssetTypeDistributionConfiguration::DISTRIBUTION_SERVICES_KEY)
                    ->defaultValue([])
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode(ExtSystemAssetTypeDistributionConfiguration::DISTRIBUTION_REQUIREMENTS_KEY)
                ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode(ExtSystemAssetTypeDistributionRequirementConfiguration::TITLE_KEY)->end()
                            ->arrayNode('blocked_by')
                                ->defaultValue([])
                                ->scalarPrototype()->end()
                            ->end()
                            ->arrayNode('category_select')
                                ->addDefaultsIfNotSet()
                                ->canBeEnabled()
                                ->children()
                                    ->booleanNode('required')->defaultFalse()->end()
                                ->end()
                            ->end()
                            ->scalarNode('strategy')
                                ->defaultValue(DistributionStrategy::NONE)
                                ->isRequired()
                            ->end()
                            ->append($this::addTextMapperConfiguration(ExtSystemAssetTypeDistributionRequirementConfiguration::DISTRIBUTION_METADATA_MAP))
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function addFileExtSystemSection(AssetType $type): NodeDefinition
    {
        $config = (new TreeBuilder($type->toString()))->getRootNode()
            ->addDefaultsIfNotSet()
            ->children()
                ->append($this->addDistributionAssetTypeSection())
                ->booleanNode(ExtSystemAssetTypeConfiguration::ENABLED_KEY)
                    ->defaultFalse()
                ->end()
                ->scalarNode(ExtSystemAssetTypeConfiguration::STORAGE_NAME_KEY)
                    ->isRequired()
                ->end()
                ->scalarNode(ExtSystemAssetTypeConfiguration::CHUNK_STORAGE_NAME_KEY)
                    ->isRequired()
                ->end()
                ->arrayNode(ExtSystemAssetTypeConfiguration::TITLE_CONFIG_KEY)
                    ->isRequired()
                    ->scalarPrototype()->end()
                ->end()
                ->integerNode(ExtSystemAssetTypeConfiguration::SIZE_LIMIT_KEY)
                    ->isRequired()
                ->end()
                ->integerNode(ExtSystemAssetTypeConfiguration::CUSTOM_METADATA_PINNED_AMOUNT)
                    ->defaultValue(5)
                ->end()
                ->arrayNode(ExtSystemAssetTypeConfiguration::MIME_TYPES)
                    ->defaultValue($type->getAllowedMimeChoices())
                    ->validate()
                        ->ifTrue(
                            function (array $value) use ($type) {
                                foreach ($value as $item) {
                                    if (false === in_array($item, $type->getAllowedMimeChoices(), true)) {
                                        return true;
                                    }
                                }

                                return false;
                            }
                        )
                        ->thenInvalid('Invalid mime type, valid options are: ' . implode(', ', $type->getAllowedMimeChoices()))
                    ->end()
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode(ExtSystemAssetTypeConfiguration::FILE_SLOTS_KEY)
                    ->isRequired()
                    ->children()
                        ->scalarNode('default')
                            ->defaultValue('default')
                        ->end()
                        ->arrayNode('slots')
                            ->defaultValue(['default'])
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end();

        $config->append($this->addExtSystemExifMetadataSection(ExtSystemAssetTypeConfiguration::KEYWORDS_KEY));
        $config->append($this->addExtSystemExifMetadataSection(ExtSystemAssetTypeConfiguration::AUTHORS_KEY));

        if ($type->is(AssetType::Audio)) {
            $config
                ->scalarNode(ExtSystemAudioTypeConfiguration::AUDIO_PUBLIC_STORAGE)->end()
                ->scalarNode(ExtSystemAudioTypeConfiguration::PUBLIC_DOMAIN_NAME)->end()
            ;
            $config->append($this::addTextMapperConfiguration(ExtSystemAudioTypeConfiguration::PODCAST_EPISODE_RSS_MAP_KEY));
            $config->append($this::addTextMapperConfiguration(ExtSystemAudioTypeConfiguration::PODCAST_EPISODE_ENTITY_MAP_KEY));
        }

        if ($type->is(AssetType::Document)) {
            $config
                ->scalarNode(ExtSystemDocumentTypeConfiguration::DOCUMENT_PUBLIC_STORAGE)->end()
                ->scalarNode(ExtSystemDocumentTypeConfiguration::PUBLIC_DOMAIN_NAME)->end()
            ;
        }

        $config->append($this::addTextMapperConfiguration(ExtSystemAssetTypeConfiguration::ASSET_EXTERNAL_PROVIDERS_MAP_KEY));

        if ($type->is(AssetType::Image)) {
            $config
                ->scalarNode(ExtSystemImageTypeConfiguration::PUBLIC_DOMAIN_KEY)
                    ->isRequired()
                ->end()
                ->scalarNode(ExtSystemImageTypeConfiguration::ADMIN_DOMAIN_KEY)
                    ->isRequired()
                ->end()
                ->scalarNode(ExtSystemImageTypeConfiguration::ROI_WIDTH_KEY)
                    ->isRequired()
                ->end()
                ->scalarNode(ExtSystemImageTypeConfiguration::ROI_HEIGHT_KEY)
                    ->isRequired()
                ->end()
                ->scalarNode(ExtSystemImageTypeConfiguration::CROP_STORAGE_NAME)
                    ->isRequired()
                ->end();
        }

        if ($type->is(AssetType::Video)) {
            $config->append($this::addTextMapperConfiguration(ExtSystemVideoTypeConfiguration::VIDEO_EPISODE_ENTITY_MAP_KEY));
        }

        return $config->end();
    }

    private function addExtSystemExifMetadataSection(string $name): NodeDefinition
    {
        return (new TreeBuilder($name))->getRootNode()
            ->canBeEnabled()
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode(ExtSystemAssetTypeExifMetadataConfiguration::REQUIRED_KEY)
                    ->defaultFalse()
                ->end()
                ->arrayNode(ExtSystemAssetTypeExifMetadataConfiguration::AUTOCOMPLETE_FROM_EXIF_METADATA_TAGS_KEY)
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->scalarPrototype()->end()
                    ->validate()
                        ->ifTrue(fn (array $values) => array_is_list($values))
                        ->thenInvalid('Must be key => value')
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addFileOperationsSection(): NodeDefinition
    {
        return (new TreeBuilder('file_operations'))->getRootNode()
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode(FileSystemProvider::TMP_STORAGE_SETTINGS)
                    ->info('Path to directory, used for file operations (merge chunks etc.)')
                    ->isRequired()
                ->end()
                ->scalarNode(FileSystemProvider::FIXTURES_STORAGE_SETTINGS)
                    ->info('Path to directory, used for generating image fixtures')
                    ->isRequired()
                ->end()
            ->end();
    }

    private function addExifMetadataSection(): NodeDefinition
    {
        return (new TreeBuilder('exif_metadata'))->getRootNode()
            ->append($this->addSpecificMetadataSection('common_metadata'))
            ->append($this->addSpecificMetadataSection('image_metadata'))
        ;
    }

    private function addSpecificMetadataSection(string $metadata): NodeDefinition
    {
        return (new TreeBuilder($metadata))->getRootNode()
            ->useAttributeAsKey('name')
            ->arrayPrototype()
                ->children()
                    ->scalarNode('autoFillField')
                        ->defaultTrue()
                    ->end()
                ->end()
            ->end();
    }

    private function addDomainsSection(): NodeDefinition
    {
        return (new TreeBuilder('domains'))->getRootNode()
            ->useAttributeAsKey('name')
            ->arrayPrototype()
                ->children()
                    ->scalarNode('domain')->end()
                    ->scalarNode(CacheConfiguration::MAX_AGE)
                        ->defaultValue(0)
                    ->end()
                    ->scalarNode(CacheConfiguration::CACHE_TTL)
                        ->defaultValue(0)
                    ->end()
                    ->booleanNode(CacheConfiguration::PUBLIC)
                        ->defaultFalse()
                    ->end()
                ->end()
            ->end();
    }

    private function addImageSection(): NodeDefinition
    {
        return (new TreeBuilder('image_settings'))->getRootNode()
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode(ConfigurationProvider::IMAGE_SETTINGS_OPTIMAL_RESIZES)
                    ->scalarPrototype()->end()
                ->isRequired()
                ->end()
                ->arrayNode('color_set')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->arrayNode('rgb')
                                ->scalarPrototype()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->end()
                ->booleanNode(ConfigurationProvider::ENABLE_CROP_CACHE)
                    ->defaultTrue()
                ->end()
                ->arrayNode('crop_allow_map')
                    ->info('Maps crop_allow_list to ext systems and domains')
                    ->performNoDeepMerging()
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('crop_allow_list')
                                ->isRequired()
                            ->end()
                            ->scalarNode('domain')
                                ->isRequired()
                            ->end()
                            ->arrayNode('ext_system_slugs')
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('crop_allow_list')
                    ->performNoDeepMerging()
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->arrayNode(CropAllowListConfiguration::QUALITY_ALLOW_LIST)
                                ->scalarPrototype()->end()
                            ->end()
                            ->arrayNode(CropAllowListConfiguration::CROPS)
                                ->arrayPrototype()
                                    ->children()
                                        ->integerNode(AllowListConfiguration::CROP_ALLOW_ITEM_WIDTH)->end()
                                        ->integerNode(AllowListConfiguration::CROP_ALLOW_ITEM_HEIGHT)->end()
                                        ->arrayNode('tags')
                                            ->scalarPrototype()->end()
                                        ->end()
                                        ->scalarNode(AllowListConfiguration::CROP_ALLOW_ITEM_TITLE)
                                            ->defaultValue('')
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }
}
