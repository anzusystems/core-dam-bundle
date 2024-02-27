<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use AnzuSystems\CommonBundle\AnzuSystemsCommonBundle;
use AnzuSystems\CommonBundle\Domain\Job\JobManager;
use AnzuSystems\CommonBundle\Exception\Handler\ValidationExceptionHandler;
use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures as BaseAssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusFacadeProvider;
use AnzuSystems\CoreDamBundle\Domain\AssetLicence\AssetLicenceManager;
use AnzuSystems\CoreDamBundle\Domain\AssetLicenceGroup\AssetLicenceGroupManager;
use AnzuSystems\CoreDamBundle\Domain\AssetSlot\AssetSlotFactory;
use AnzuSystems\CoreDamBundle\Domain\CustomForm\CustomFormFactory;
use AnzuSystems\CoreDamBundle\Domain\CustomForm\CustomFormManager;
use AnzuSystems\CoreDamBundle\Domain\DistributionCategory\DistributionCategoryManager;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemManager;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFactory;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageManager;
use AnzuSystems\CoreDamBundle\Domain\User\UserManager;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceGroupFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\DistributionCategoryFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\UserFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\CustomFormElementFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ImageFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\JobFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\SystemUserFixtures;
use AnzuSystems\CoreDamBundle\Tests\HttpClient\BaseClient;
use AnzuSystems\CoreDamBundle\Tests\HttpClient\JwClientMock;
use AnzuSystems\CoreDamBundle\Tests\HttpClient\RssPodcastMock;
use Doctrine\ORM\EntityManagerInterface;
use Redis;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $configurator
        ->parameters()
        ->set('app_cache_proxy_enabled', true);

    $services->set(SystemUserFixtures::class)
        ->arg('$userManager', service(UserManager::class))
        ->call('setEntityManager', [service(EntityManagerInterface::class)])
        ->tag(AnzuSystemsCommonBundle::TAG_DATA_FIXTURE);

    $services->set(ExtSystemFixtures::class)
        ->arg('$extSystemManager', service(ExtSystemManager::class))
        ->call('setEntityManager', [service(EntityManagerInterface::class)])
        ->tag(AnzuSystemsCommonBundle::TAG_DATA_FIXTURE);

    $services->set(AssetLicenceFixtures::class)
        ->arg('$assetLicenceManager', service(AssetLicenceManager::class))
        ->call('setEntityManager', [service(EntityManagerInterface::class)])
        ->tag(AnzuSystemsCommonBundle::TAG_DATA_FIXTURE);

    $services->set(AssetLicenceGroupFixtures::class)
        ->arg('$assetLicenceGroupManager', service(AssetLicenceGroupManager::class))
        ->call('setEntityManager', [service(EntityManagerInterface::class)])
        ->tag(AnzuSystemsCommonBundle::TAG_DATA_FIXTURE);

    $services->set(DistributionCategoryFixtures::class)
        ->arg('$distributionCategoryManager', service(DistributionCategoryManager::class))
        ->call('setEntityManager', [service(EntityManagerInterface::class)])
        ->tag(AnzuSystemsCommonBundle::TAG_DATA_FIXTURE);

    $services->set(CustomFormElementFixtures::class)
        ->arg('$customFormManager', service(CustomFormManager::class))
        ->arg('$customFormFactory', service(CustomFormFactory::class))
        ->call('setEntityManager', [service(EntityManagerInterface::class)])
        ->tag(AnzuSystemsCommonBundle::TAG_DATA_FIXTURE);

    $services->set(UserFixtures::class)
        ->arg('$userManager', service(UserManager::class))
        ->arg('$assetLicenceFixtures', service(AssetLicenceFixtures::class))
        ->arg('$baseAssetLicenceFixtures', service(BaseAssetLicenceFixtures::class))
        ->call('setEntityManager', [service(EntityManagerInterface::class)])
        ->tag(AnzuSystemsCommonBundle::TAG_DATA_FIXTURE);

    $services->set(JobFixtures::class)
        ->arg('$jobManager', service(JobManager::class))
        ->call('setEntityManager', [service(EntityManagerInterface::class)])
        ->tag(AnzuSystemsCommonBundle::TAG_DATA_FIXTURE);

    $services->set(ImageFixtures::class)
        ->arg('$imageManager', service(ImageManager::class))
        ->arg('$imageFactory', service(ImageFactory::class))
        ->arg('$licenceRepository', service(AssetLicenceRepository::class))
        ->arg('$fileSystemProvider', service(FileSystemProvider::class))
        ->arg('$facadeProvider', service(AssetFileStatusFacadeProvider::class))
        ->arg('$assetSlotFactory', service(AssetSlotFactory::class))
        ->call('setEntityManager', [service(EntityManagerInterface::class)])
        ->tag(AnzuSystemsCommonBundle::TAG_DATA_FIXTURE);

    $services->set('TestRedis', Redis::class)
        ->call('connect', [env('REDIS_HOST'), env('REDIS_PORT')->int()])
        ->call('select', [env('REDIS_DB')->int()])
        ->call('setOption', [Redis::OPT_PREFIX, 'common_bundle_' . env('APP_ENV')])
    ;

    $services->set(JwClientMock::class);
    $services->set(HttpClientInterface::class . ' $jwPlayerApiClient', MockHttpClient::class)
        ->factory(service(JwClientMock::class));
    $services->set(BaseClient::class);
    $services->set(HttpClientInterface::class . ' $client', MockHttpClient::class)
        ->factory(service(BaseClient::class));

    $services->set(RssPodcastMock::class);
    $services->set(HttpClientInterface::class . ' $httpClient', MockHttpClient::class)
        ->factory(service(RssPodcastMock::class));

    $services->set(ValidationExceptionHandler::class)
        ->tag(AnzuSystemsCommonBundle::TAG_EXCEPTION_HANDLER);
};
