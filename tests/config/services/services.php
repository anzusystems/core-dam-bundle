<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use AnzuSystems\CommonBundle\AnzuSystemsCommonBundle;
use AnzuSystems\CommonBundle\Exception\Handler\ValidationExceptionHandler;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemManager;
use AnzuSystems\CoreDamBundle\Domain\User\UserManager;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\UserFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Redis;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services->set(UserFixtures::class)
        ->arg('$userManager', service(UserManager::class))
        ->call('setEntityManager', [service(EntityManagerInterface::class)])
        ->tag(AnzuSystemsCommonBundle::TAG_DATA_FIXTURE);

    $services->set(ExtSystemFixtures::class)
        ->arg('$extSystemManager', service(ExtSystemManager::class))
        ->call('setEntityManager', [service(EntityManagerInterface::class)])
        ->tag(AnzuSystemsCommonBundle::TAG_DATA_FIXTURE);

    $services->set('TestRedis', Redis::class)
        ->call('connect', [env('REDIS_HOST'), env('REDIS_PORT')->int()])
        ->call('select', [env('REDIS_DB')->int()])
        ->call('setOption', [Redis::OPT_PREFIX, 'common_bundle_' . env('APP_ENV')])
    ;

    $services->set(ValidationExceptionHandler::class)
        ->tag(AnzuSystemsCommonBundle::TAG_EXCEPTION_HANDLER);

};
