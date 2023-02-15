<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DependencyInjection;

use AnzuSystems\CoreDamBundle\AnzuSystemsCoreDamBundle;
use AnzuSystems\CoreDamBundle\AssetExternalProvider\AssetExternalProviderContainer;
use AnzuSystems\CoreDamBundle\Doctrine\Type\ColorType;
use AnzuSystems\CoreDamBundle\Doctrine\Type\OriginExternalProviderType;
use AnzuSystems\CoreDamBundle\Domain\Configuration\AllowListConfiguration;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\FileSystem\Adapter\LocalFileSystemAdapter;
use AnzuSystems\CoreDamBundle\FileSystem\GCloudFilesystem;
use AnzuSystems\CoreDamBundle\FileSystem\LocalFilesystem;
use AnzuSystems\CoreDamBundle\FileSystem\StorageProviderContainer;
use AnzuSystems\CoreDamBundle\Messenger\Message\AssetChangeStateMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\AssetFileMetadataProcessMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\AssetRefreshPropertiesMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\AudioFileChangeStateMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\DistributeMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\DistributionRemoteProcessingCheckMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\DocumentFileChangeStateMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\ImageFileChangeStateMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\VideoFileChangeStateMessage;
use AnzuSystems\CoreDamBundle\Model\Configuration\AssetExternalProviderConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\CropAllowListConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\DistributionServiceConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAssetExternalProviderConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAssetTypeDistributionRequirementConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemImageTypeConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\SettingsConfiguration;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use Exception;
use League\Flysystem\GoogleCloudStorage\GoogleCloudStorageAdapter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

final class AnzuSystemsCoreDamExtension extends Extension implements PrependExtensionInterface
{
    public const STORAGE_DEFINITION_NAME_PREFIX = 'core_dam_bundle.storage.';

    private array $processedConfig = [];

    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('doctrine', [
            'dbal' => [
                'types' => [
                    ColorType::NAME => ColorType::class,
                    OriginExternalProviderType::NAME => OriginExternalProviderType::class,
                ],
            ],
            'orm' => [
                'mappings' => [
                    'AnzuSystemsCoreDamBundle' => [
                        'is_bundle' => true,
                        'type' => 'attribute',
                        'dir' => 'Entity',
                        'prefix' => 'AnzuSystems\CoreDamBundle\Entity',
                        'alias' => 'AnzuSystems\CoreDamBundle',
                    ],
                    'AnzuSystemsCommonBundle' => [
                        'is_bundle' => true,
                        'type' => 'attribute',
                        'dir' => 'Entity',
                        'prefix' => 'AnzuSystems\CommonBundle\Entity',
                        'alias' => 'AnzuSystems\CommonBundle',
                    ],
                ],
            ],
        ]);

        $applicationName = 'core_dam';

        $imageFileChangeStateTopic = '%env(MESSENGER_IMAGE_FILE_CHANGE_STATE_TOPIC)%';
        $imageFileChangeStateTopicDsn = "%env(MESSENGER_TRANSPORT_DSN)%/{$imageFileChangeStateTopic}";
        $videoFileChangeStateTopic = '%env(MESSENGER_VIDEO_FILE_CHANGE_STATE_TOPIC)%';
        $videoFileChangeStateTopicDsn = "%env(MESSENGER_TRANSPORT_DSN)%/{$videoFileChangeStateTopic}";
        $documentFileChangeStateTopic = '%env(MESSENGER_DOCUMENT_FILE_CHANGE_STATE_TOPIC)%';
        $documentFileChangeStateTopicDsn = "%env(MESSENGER_TRANSPORT_DSN)%/{$documentFileChangeStateTopic}";
        $audioFileChangeStateTopic = '%env(MESSENGER_AUDIO_FILE_CHANGE_STATE_TOPIC)%';
        $audioFileChangeStateTopicDsn = "%env(MESSENGER_TRANSPORT_DSN)%/{$audioFileChangeStateTopic}";
        $assetDeleteTopic = '%env(MESSENGER_ASSET_CHANGE_STATE_TOPIC)%';
        $assetDeleteTopicDsn = "%env(MESSENGER_TRANSPORT_DSN)%/{$assetDeleteTopic}";
        $assetFileMetadataProcessTopic = '%env(MESSENGER_ASSET_METADATA_PROCESS_TOPIC)%';
        $assetFileMetadataProcessTopicDsn = "%env(MESSENGER_TRANSPORT_DSN)%/{$assetFileMetadataProcessTopic}";
        $distributionTopic = '%env(MESSENGER_DISTRIBUTION_TOPIC)%';
        $distributionTopicDsn = "%env(MESSENGER_TRANSPORT_DSN)%/{$distributionTopic}";
        $distributionRemoteProcessedCheckTopic = '%env(MESSENGER_DISTRIBUTION_REMOTE_PROCESSED_CHECK_TOPIC)%';
        $distributionRemoteProcessedCheckTopicDsn = "%env(MESSENGER_TRANSPORT_DSN)%/{$distributionRemoteProcessedCheckTopic}";

        $assetPropertyRefreshTopic = '%env(MESSENGER_PROPERTY_REFRESH_TOPIC)%';
        $assetPropertyRefreshTopicDsn = "%env(MESSENGER_TRANSPORT_DSN)%/{$assetPropertyRefreshTopic}";

        $container->prependExtensionConfig('framework', [
            'messenger' => [
                'transports' => [
                    $distributionRemoteProcessedCheckTopic => [
                        'dsn' => $distributionRemoteProcessedCheckTopicDsn,
                        'options' => [
                            'topic' => [
                                'name' => $distributionRemoteProcessedCheckTopic,
                                'options' => [
                                    'labels' => [
                                        'application' => $applicationName,
                                        'name' => $distributionRemoteProcessedCheckTopic,
                                        'topic' => $distributionRemoteProcessedCheckTopic,
                                    ],
                                ],
                            ],
                            'subscription' => [
                                'name' => $distributionRemoteProcessedCheckTopic,
                                'options' => [
                                    'labels' => [
                                        'application' => $applicationName,
                                        'name' => $distributionRemoteProcessedCheckTopic,
                                    ],
                                    'ack_deadline_seconds' => '60s',
                                    'retryPolicy' => [
                                        'minimumBackoff' => '30s',
                                        'maximumBackoff' => '90s',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    $imageFileChangeStateTopic => [
                        'dsn' => $imageFileChangeStateTopicDsn,
                        'options' => [
                            'topic' => [
                                'name' => $imageFileChangeStateTopic,
                                'options' => [
                                    'labels' => [
                                        'application' => $applicationName,
                                        'name' => $imageFileChangeStateTopic,
                                        'topic' => $imageFileChangeStateTopic,
                                    ],
                                ],
                            ],
                            'subscription' => [
                                'name' => $imageFileChangeStateTopic,
                                'options' => [
                                    'labels' => [
                                        'application' => $applicationName,
                                        'name' => $imageFileChangeStateTopic,
                                    ],
                                    'ack_deadline_seconds' => '60s',
                                    'retryPolicy' => [
                                        'minimumBackoff' => '2s',
                                        'maximumBackoff' => '600s',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    $documentFileChangeStateTopic => [
                        'dsn' => $documentFileChangeStateTopicDsn,
                        'options' => [
                            'topic' => [
                                'name' => $documentFileChangeStateTopic,
                                'options' => [
                                    'labels' => [
                                        'application' => $applicationName,
                                        'name' => $documentFileChangeStateTopic,
                                        'topic' => $documentFileChangeStateTopic,
                                    ],
                                ],
                            ],
                            'subscription' => [
                                'name' => $documentFileChangeStateTopic,
                                'options' => [
                                    'labels' => [
                                        'application' => $applicationName,
                                        'name' => $documentFileChangeStateTopic,
                                    ],
                                    'ack_deadline_seconds' => '120s',
                                    'retryPolicy' => [
                                        'minimumBackoff' => '2s',
                                        'maximumBackoff' => '600s',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    $audioFileChangeStateTopic => [
                        'dsn' => $audioFileChangeStateTopicDsn,
                        'options' => [
                            'topic' => [
                                'name' => $audioFileChangeStateTopic,
                                'options' => [
                                    'labels' => [
                                        'application' => $applicationName,
                                        'name' => $audioFileChangeStateTopic,
                                        'topic' => $audioFileChangeStateTopic,
                                    ],
                                ],
                            ],
                            'subscription' => [
                                'name' => $audioFileChangeStateTopic,
                                'options' => [
                                    'labels' => [
                                        'application' => $applicationName,
                                        'name' => $audioFileChangeStateTopic,
                                    ],
                                    'ack_deadline_seconds' => '300s',
                                    'retryPolicy' => [
                                        'minimumBackoff' => '2s',
                                        'maximumBackoff' => '600s',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    $videoFileChangeStateTopic => [
                        'dsn' => $videoFileChangeStateTopicDsn,
                        'options' => [
                            'topic' => [
                                'name' => $videoFileChangeStateTopic,
                                'options' => [
                                    'labels' => [
                                        'application' => $applicationName,
                                        'name' => $videoFileChangeStateTopic,
                                        'topic' => $videoFileChangeStateTopic,
                                    ],
                                ],
                            ],
                            'subscription' => [
                                'name' => $videoFileChangeStateTopic,
                                'options' => [
                                    'labels' => [
                                        'application' => $applicationName,
                                        'name' => $videoFileChangeStateTopic,
                                    ],
                                    'ack_deadline_seconds' => '500s',
                                    'retryPolicy' => [
                                        'minimumBackoff' => '2s',
                                        'maximumBackoff' => '600s',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    $assetDeleteTopic => [
                        'dsn' => $assetDeleteTopicDsn,
                        'options' => [
                            'topic' => [
                                'name' => $assetDeleteTopic,
                                'options' => [
                                    'labels' => [
                                        'application' => $applicationName,
                                        'name' => $assetDeleteTopic,
                                        'topic' => $assetDeleteTopic,
                                    ],
                                ],
                            ],
                            'subscription' => [
                                'name' => $assetDeleteTopic,
                                'options' => [
                                    'labels' => [
                                        'application' => $applicationName,
                                        'name' => $assetDeleteTopic,
                                    ],
                                    'retryPolicy' => [
                                        'minimumBackoff' => '2s',
                                        'maximumBackoff' => '600s',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    $assetFileMetadataProcessTopic => [
                        'dsn' => $assetFileMetadataProcessTopicDsn,
                        'options' => [
                            'topic' => [
                                'name' => $assetFileMetadataProcessTopic,
                                'options' => [
                                    'labels' => [
                                        'application' => $applicationName,
                                        'name' => $assetFileMetadataProcessTopic,
                                        'topic' => $assetFileMetadataProcessTopic,
                                    ],
                                ],
                            ],
                            'subscription' => [
                                'name' => $assetFileMetadataProcessTopic,
                                'options' => [
                                    'labels' => [
                                        'application' => $applicationName,
                                        'name' => $assetFileMetadataProcessTopic,
                                    ],
                                    'retryPolicy' => [
                                        'minimumBackoff' => '2s',
                                        'maximumBackoff' => '600s',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    $distributionTopic => [
                        'dsn' => $distributionTopicDsn,
                        'options' => [
                            'topic' => [
                                'name' => $distributionTopic,
                                'options' => [
                                    'labels' => [
                                        'application' => $applicationName,
                                        'name' => $distributionTopic,
                                        'topic' => $distributionTopic,
                                    ],
                                ],
                            ],
                            'subscription' => [
                                'name' => $distributionTopic,
                                'options' => [
                                    'labels' => [
                                        'application' => $applicationName,
                                        'name' => $distributionTopic,
                                    ],
                                    'ack_deadline_seconds' => '300s',
                                    'retryPolicy' => [
                                        'minimumBackoff' => '2s',
                                        'maximumBackoff' => '600s',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    $assetPropertyRefreshTopic => [
                        'dsn' => $assetPropertyRefreshTopicDsn,
                        'options' => [
                            'topic' => [
                                'name' => $assetPropertyRefreshTopic,
                                'options' => [
                                    'labels' => [
                                        'application' => $applicationName,
                                        'name' => $assetPropertyRefreshTopic,
                                        'topic' => $assetPropertyRefreshTopic,
                                    ],
                                ],
                            ],
                            'subscription' => [
                                'name' => $assetPropertyRefreshTopic,
                                'options' => [
                                    'labels' => [
                                        'application' => $applicationName,
                                        'name' => $assetPropertyRefreshTopic,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'routing' => [
                    VideoFileChangeStateMessage::class => $videoFileChangeStateTopic,
                    AudioFileChangeStateMessage::class => $audioFileChangeStateTopic,
                    DocumentFileChangeStateMessage::class => $documentFileChangeStateTopic,
                    ImageFileChangeStateMessage::class => $imageFileChangeStateTopic,
                    AssetChangeStateMessage::class => $assetDeleteTopic,
                    AssetFileMetadataProcessMessage::class => $assetFileMetadataProcessTopic,
                    DistributeMessage::class => $distributionTopic,
                    DistributionRemoteProcessingCheckMessage::class => $distributionRemoteProcessedCheckTopic,
                    AssetRefreshPropertiesMessage::class => $assetPropertyRefreshTopic,
                ],
            ],
            'http_client' => [
                'scoped_clients' => [
                    'jwPlayer.api.client' => [
                        'base_uri' => 'https://api.jwplayer.com', // todo env
                    ],
                    'unsplash.api.client' => [
                        'base_uri' => 'https://api.unsplash.com', // todo env
                        'headers' => [
                            'Accept-Version' => 'v1',
                        ],
                    ],
                ],
            ],
        ]);

        foreach ($container->getExtensionConfig($this->getAlias()) as $config) {
            if (array_key_exists('settings', $config)) {
                $configSettings = $config['settings'];
                $container->prependExtensionConfig('framework', [
                    'cache' => [
                        'pools' => [
                            'core_dam_bundle.asset_external_provider_cache' => [
                                'adapter' => 'cache.adapter.redis',
                                'provider' => $configSettings[SettingsConfiguration::CACHE_REDIS_KEY],
                                'default_lifetime' => 'PT3M',
                            ],
                            'core_dam_bundle.counter_cache' => [
                                'adapter' => 'cache.adapter.redis',
                                'provider' => $configSettings[SettingsConfiguration::CACHE_REDIS_KEY],
                                'default_lifetime' => 'P1D',
                            ],
                            'core_dam_bundle.youtube_cache' => [
                                'adapter' => 'cache.adapter.redis',
                                'provider' => $configSettings[SettingsConfiguration::CACHE_REDIS_KEY],
                                'default_lifetime' => 'PT1M',
                            ],
                        ],
                    ],
                ]);

                if (true === ($configSettings['notifications']['enabled'] ?? true)) {
                    $notificationTopic = '%env(MESSENGER_NOTIFICATION_TOPIC)%';
                    $notificationTopicDsn = "%env(MESSENGER_TRANSPORT_DSN)%/{$notificationTopic}";
                    $container->prependExtensionConfig('anzu_systems_core_dam', [
                        'settings' => [
                            'notifications' => [
                                'topic' => $notificationTopic,
                            ],
                        ],
                    ]);
                    $container->prependExtensionConfig('framework', [
                        'cache' => [
                            'pools' => [
                                'core_dam_bundle.pub_sub_token_cache' => [
                                    'adapter' => 'cache.adapter.redis',
                                    'provider' => $configSettings[SettingsConfiguration::CACHE_REDIS_KEY],
                                    'default_lifetime' => '1 day',
                                ],
                            ],
                        ],
                        'messenger' => [
                            'transports' => [
                                $notificationTopic => [
                                    'dsn' => $notificationTopicDsn,
                                    'options' => [
                                        'topic' => [
                                            'name' => $notificationTopic,
                                            'options' => [
                                                'labels' => [
                                                    'application' => $applicationName,
                                                    'name' => $notificationTopic,
                                                    'topic' => $notificationTopic,
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ]);
                }
            }
        }
    }

    /**
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $this->processedConfig = $this->processConfiguration($configuration, $configs);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.php');
        $this->addDefaultsToExtSystemsAssetDistributions();
        $this->addDefaultsToExtSystemsAssetExternalProviders();
        $container->setParameter('anzu_systems.dam_bundle.settings', $this->processedConfig['settings']);
        $container->setParameter('anzu_systems.dam_bundle.ext_systems', $this->processedConfig['ext_systems']);
        $container->setParameter('anzu_systems.dam_bundle.file_operations', $this->processedConfig['file_operations']);
        $container->setParameter('anzu_systems.dam_bundle.image_settings', $this->processedConfig['image_settings']);
        $container->setParameter('anzu_systems.dam_bundle.display_title', $this->processedConfig['display_title']);
        $container->setParameter('anzu_systems.dam_bundle.domains', $this->processedConfig['domains']);
        $container->setParameter('anzu_systems.dam_bundle.distribution_services', $this->processedConfig['distribution_services']);
        $container->setParameter('anzu_systems.dam_bundle.asset_external_providers', $this->processedConfig['asset_external_providers']);
        $container->setParameter('anzu_systems.dam_bundle.common_metadata', $this->processedConfig['exif_metadata']['common_metadata']);
        $container->setParameter('anzu_systems.dam_bundle.image_metadata', $this->processedConfig['exif_metadata']['image_metadata']);
        $container->setParameter('anzu_systems.dam_bundle.crop_allow_list', $this->processedConfig['image_settings']['crop_allow_list']);

        $tagGroups = [];
        foreach ($this->processedConfig['image_settings']['crop_allow_list'] as $name => $allowList) {
            if (false === isset($tagGroups[$name])) {
                $tagGroups[$name] = [];
            }

            foreach ($allowList['crops'] as $crop) {
                foreach ($crop['tags'] as $tag) {
                    if (false === isset($tagGroups[$name][$tag])) {
                        $tagGroups[$name][$tag] = [];
                    }
                    $tagGroups[$name][$tag][] = [
                        AllowListConfiguration::CROP_ALLOW_ITEM_WIDTH => $crop[AllowListConfiguration::CROP_ALLOW_ITEM_WIDTH],
                        AllowListConfiguration::CROP_ALLOW_ITEM_HEIGHT => $crop[AllowListConfiguration::CROP_ALLOW_ITEM_HEIGHT],
                        AllowListConfiguration::CROP_ALLOW_ITEM_TITLE => $crop[AllowListConfiguration::CROP_ALLOW_ITEM_TITLE],
                    ];
                }
            }
        }
        $container->setParameter('anzu_systems.dam_bundle.tagged_allow_list', $tagGroups);

        $domainAllowList = [];
        foreach ($this->processedConfig['image_settings']['crop_allow_list'] as $allowList) {
            $domainAllowList[$this->getDomain($allowList[CropAllowListConfiguration::DOMAIN])] = $allowList;
        }
        $container->setParameter('anzu_systems.dam_bundle.domain_allow_list', $domainAllowList);

        $colors = [];
        foreach ($this->processedConfig['image_settings']['color_set'] as $name => $color) {
            $colors[$name] = $color['rgb'];
        }
        $container->setParameter('anzu_systems.dam_bundle.color_set', $colors);

        $domainNames = [];
        foreach ($this->processedConfig['domains'] as $name => $domainConfig) {
            $domainNames[$domainConfig['domain']] = $name;
        }
        $container->setParameter('anzu_systems.dam_bundle.domain_names', $domainNames);
        $this->configureStorages($container);

        $assetExternalProviders = [];
        foreach ($this->processedConfig['asset_external_providers'] as $providerName => $provider) {
            $container
                ->register($provider['provider'])
                ->setAutowired(true)
                ->addMethodCall('setConfiguration', [$provider])
            ;
            $assetExternalProviders[$providerName] = new Reference($provider['provider']);
        }

        $assetExternalProviderContainer = $container->findDefinition(AssetExternalProviderContainer::class);
        $assetExternalProviderContainer->setArgument(
            '$providersContainer',
            ServiceLocatorTagPass::register($container, $assetExternalProviders)
        );
    }

    private function configureStorages(ContainerBuilder $container): void
    {
        $storages = [];
        foreach ($this->processedConfig['storages'] as $storageName => $storageConfig) {
            $filesystem = $this->configureFilesystem($storageConfig);

            if ($filesystem) {
                $definitionName = self::STORAGE_DEFINITION_NAME_PREFIX . $storageName;

                $filesystem->addMethodCall('setFallbackEnabled', [$storageConfig['fallback_enabled']]);
                if ($storageConfig['fallback_enabled'] && false === empty($storageConfig['fallback_storage'])) {
                    $filesystem->addMethodCall('setFallbackStorage', [new Reference(
                        self::STORAGE_DEFINITION_NAME_PREFIX . $storageConfig['fallback_storage']
                    )]);
                }

                $filesystem->addTag(AnzuSystemsCoreDamBundle::TAG_FILESYSTEM, ['key' => $storageName]);
                $storages[$storageName] = new Reference($definitionName);
                $container->setDefinition($definitionName, $filesystem);
            }
        }

        $storageProviderContainer = $container->findDefinition(StorageProviderContainer::class);
        $storageProviderContainer->setArgument(
            '$storagesContainer',
            ServiceLocatorTagPass::register($container, $storages)
        );
    }

    private function configureFilesystem(array $storageConfig): ?Definition
    {
        if ('local' === $storageConfig['adapter']) {
            return $this->configureLocalSystem($storageConfig['options']);
        }
        if ('gcloud' === $storageConfig['adapter']) {
            return $this->configureGcloudSystem($storageConfig['options']);
        }

        return null;
    }

    private function configureLocalSystem(array $storageOptions): ?Definition
    {
        $adapter = new Definition();
        $adapter->setClass(LocalFileSystemAdapter::class);
        $adapter->setArgument('$location', $storageOptions['directory']);

        $filesystem = new Definition(LocalFilesystem::class);
        $filesystem->setArgument('$adapter', $adapter);
        $filesystem->setArgument('$directory', $storageOptions['directory']);

        return $filesystem;
    }

    private function configureGcloudSystem(array $storageOptions): Definition
    {
        $prefix = $storageOptions['prefix'] ?? '';

        $bucketDefinition = new Definition();
        $bucketDefinition->setFactory([new Reference($storageOptions['client']), 'bucket']);
        $bucketDefinition->setArgument(0, $storageOptions['bucket']);

        $adapter = new Definition();
        $adapter->setClass(GoogleCloudStorageAdapter::class);
        $adapter->setArgument('$bucket', $bucketDefinition);
        $adapter->setArgument('$prefix', $prefix);

        $filesystem = new Definition(GCloudFilesystem::class);
        $filesystem->setArgument('$adapter', $adapter);
        $filesystem->setArgument('$prefix', $prefix);
        $filesystem->setArgument('$bucket', $bucketDefinition);

        return $filesystem;
    }

    private function getDomain(string $key): string
    {
        if (isset($this->processedConfig['domains'][$key]['domain'])) {
            return $this->processedConfig['domains'][$key]['domain'];
        }

        throw new RuntimeException("Domain ({$key}) not found");
    }

    private function addDefaultsToExtSystemsAssetDistributions(): void
    {
        foreach ($this->processedConfig['ext_systems'] as $extSystemSlug => $extSystemConfig) {
            foreach ($extSystemConfig as $assetType => $assetExtSystemConfig) {
                if (AssetType::Image->toString() === $assetType) {
                    $this->processedConfig['ext_systems'][$extSystemSlug][$assetType][ExtSystemImageTypeConfiguration::PUBLIC_DOMAIN_KEY] = $this->getDomain($this->processedConfig['ext_systems'][$extSystemSlug][$assetType][ExtSystemImageTypeConfiguration::PUBLIC_DOMAIN_KEY]);
                    $this->processedConfig['ext_systems'][$extSystemSlug][$assetType][ExtSystemImageTypeConfiguration::ADMIN_DOMAIN_KEY] = $this->getDomain($this->processedConfig['ext_systems'][$extSystemSlug][$assetType][ExtSystemImageTypeConfiguration::ADMIN_DOMAIN_KEY]);
                }

                $distRequirements = $assetExtSystemConfig['distribution']['distribution_requirements'] ?? [];
                foreach ($distRequirements as $name => $distRequirement) {
                    $this->processedConfig['ext_systems'][$extSystemSlug][$assetType]['distribution']['distribution_requirements'][$name][ExtSystemAssetTypeDistributionRequirementConfiguration::DISTRIBUTION_SERVICE_ID_KEY] = $name;
                    $this->processedConfig['ext_systems'][$extSystemSlug][$assetType]['distribution']['distribution_requirements'][$name][ExtSystemAssetTypeDistributionRequirementConfiguration::REQUIRED_AUTH_KEY] ??= $this->processedConfig['distribution_services'][$name][DistributionServiceConfiguration::REQUIRED_AUTH_KEY];
                    $this->processedConfig['ext_systems'][$extSystemSlug][$assetType]['distribution']['distribution_requirements'][$name][ExtSystemAssetTypeDistributionRequirementConfiguration::TITLE_KEY] ??= $this->processedConfig['distribution_services'][$name][DistributionServiceConfiguration::TITLE_KEY];
                }
            }
        }
    }

    private function addDefaultsToExtSystemsAssetExternalProviders(): void
    {
        foreach ($this->processedConfig['ext_systems'] as $extSystemSlug => $extSystemConfig) {
            foreach (array_keys($extSystemConfig['asset_external_providers'] ?? []) as $providerName) {
                $this->processedConfig['ext_systems'][$extSystemSlug]['asset_external_providers'][$providerName][ExtSystemAssetExternalProviderConfiguration::PROVIDER_NAME_KEY] = $providerName;

                $this->processedConfig['ext_systems'][$extSystemSlug]['asset_external_providers'][$providerName][
                    ExtSystemAssetExternalProviderConfiguration::TITLE_KEY
                ] ??= $this->processedConfig['asset_external_providers'][$providerName][AssetExternalProviderConfiguration::TITLE_KEY];

                $this->processedConfig['ext_systems'][$extSystemSlug]['asset_external_providers'][$providerName][AssetExternalProviderConfiguration::OPTIONS_KEY][
                    ExtSystemAssetExternalProviderConfiguration::LISTING_LIMIT_KEY
                ] ??= $this->processedConfig['asset_external_providers'][$providerName][AssetExternalProviderConfiguration::OPTIONS_KEY][
                    ExtSystemAssetExternalProviderConfiguration::LISTING_LIMIT_KEY
                ];
            }
        }
    }
}
