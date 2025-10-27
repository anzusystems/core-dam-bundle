<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use AnzuSystems\CoreDamBundle\Elasticsearch\IndexBuilder;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\FileSystem\NameGenerator\DirectoryNameGenerator;
use AnzuSystems\CoreDamBundle\FileSystem\NameGenerator\DirectoryNamGeneratorInterface;
use AnzuSystems\CoreDamBundle\FileSystem\NameGenerator\FileNameGenerator;
use AnzuSystems\CoreDamBundle\FileSystem\NameGenerator\FileNameGeneratorInterface;
use AnzuSystems\CoreDamBundle\Messenger\Handler\AssetFileMetadataProcessHandler;
use AnzuSystems\CoreDamBundle\Messenger\Message\DistributionRemoteProcessingCheckMessage;
use AnzuSystems\CoreDamBundle\Util\Slugger;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Symfony\Component\String\Slugger\SluggerInterface;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $configurator->import(__DIR__ . '/../../../src/Resources/config/elasticsearch/*');

    $configurator
        ->parameters()
        ->set('elastic_client_config', [
            'hosts' => env('ELASTIC_HOSTS')->json(),
            'basicAuthentication' => [
                'username' => env('ELASTIC_USERNAME')->string(),
                'password' => env('ELASTIC_PASSWORD')->string(),
            ],
        ])
        ->set('app_cache_proxy_enabled', true)
        ->set('elastic_index_settings', [
            'asset' => param('anzu_systems.dam_bundle.index_asset'),
            'keyword' => param('anzu_systems.dam_bundle.index_keyword'),
            'author' => param('anzu_systems.dam_bundle.index_author'),
            Distribution::INDEX_NAME => param('anzu_systems.dam_bundle.index_distribution'),
        ])
        ->set('app_false', false)
        ->set('elasticsearch_next_enabled', env('ELASTICSEARCH_NEXT_ENABLED')->default('app_false'))
    ;

    $services
        ->defaults()
        ->autowire(true)
        ->autoconfigure(true)

        ->bind('$searcNext', param('elasticsearch_next_enabled'))
        ->bind('$settings', param('anzu_systems.dam_bundle.settings'))
        ->bind('$redirectDomain', param('anzu_systems.dam_bundle.settings_redirect_domain'))
        ->bind('$displayTitle', param('anzu_systems.dam_bundle.display_title'))
        ->bind('$distributionServices', param('anzu_systems.dam_bundle.distribution_services'))
        ->bind('$extSystems', param('anzu_systems.dam_bundle.ext_systems'))
        ->bind('$fileOperations', param('anzu_systems.dam_bundle.file_operations'))
        ->bind('$imageSettings', param('anzu_systems.dam_bundle.image_settings'))
        ->bind('$domains', param('anzu_systems.dam_bundle.domains'))
        ->bind('$taggedAllowList', param('anzu_systems.dam_bundle.tagged_allow_list'))
        ->bind('$domainAllowList', param('anzu_systems.dam_bundle.domain_allow_list'))
        ->bind('$domainAllowMap', param('anzu_systems.dam_bundle.crop_allow_map'))
        ->bind('$extSystemAllowListMap', param('anzu_systems.dam_bundle.ext_system_allow_list_map'))
        ->bind('$domainNames', param('anzu_systems.dam_bundle.domain_names'))
        ->bind('$exifCommonMetadata', param('anzu_systems.dam_bundle.common_metadata'))
        ->bind('$exifImageMetadata', param('anzu_systems.dam_bundle.image_metadata'))
        ->bind('$colorSet', param('anzu_systems.dam_bundle.color_set'))
        ->bind('$exiftoolBin', param('kernel.project_dir') . '/vendor/phpexiftool/exiftool/exiftool')
        ->bind('$userEntityClass', param('anzu_systems.dam_bundle.settings.user_entity_class'))
    ;

    $services
        ->load('AnzuSystems\\CoreDamBundle\\', __DIR__ . '/../../../src/*')
        ->exclude([
            __DIR__ . '/../../../src/{Entity,Helper,Model,Resources,Security/Permission}',
            __DIR__ . '/../../../src/Kernel.php',
        ])
    ;

    $services->set(FileNameGeneratorInterface::class . ' $fileNameGenerator', FileNameGenerator::class);
    $services->set(DirectoryNamGeneratorInterface::class . ' $directoryNameGenerator', DirectoryNameGenerator::class);
    $services->set(SluggerInterface::class . ' $slugger', Slugger::class);

    $services->set(AssetFileMetadataProcessHandler::class)
        ->tag('messenger.message_handler', ['handler' => DistributionRemoteProcessingCheckMessage::class])
    ;

    $services
        ->set(Client::class)
        ->factory([ClientBuilder::class, 'fromConfig'])
        ->arg('$config', param('elastic_client_config'))
    ;

    $services
        ->set(IndexBuilder::class)
        ->arg('$indexMappings', param('elastic_index_settings'))
    ;
};
