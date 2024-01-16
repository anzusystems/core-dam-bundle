<?php

declare(strict_types=1);

namespace Symfony\Component\Routing\Loader\Configurator;

return static function (RoutingConfigurator $routes): void {
    $routes
        ->import('@AnzuSystemsCoreDamBundle/Controller/Api/Adm/V1', type: 'attribute')
        ->prefix('/api/adm/v1/');

    $routes
        ->import('@AnzuSystemsCoreDamBundle/Controller/Api/Sys/V1', type: 'attribute')
        ->prefix('/api/sys/v1/');

    $routes
        ->import('@AnzuSystemsCoreDamBundle/Controller/Api/Pub/V1', type: 'attribute')
        ->prefix('/api/pub/v1/');

    $routes
        ->import('@AnzuSystemsCoreDamBundle/Controller/ImageController.php', type: 'attribute')
        ->prefix('/');

    $routes
        ->import('@AnzuSystemsCoreDamBundle/Controller/AssetFileRouteController.php', type: 'attribute')
        ->prefix('/');

    $routes
        ->import('@AnzuSystemsCoreDamBundle/Controller/Adm', type: 'attribute')
        ->prefix('/adm/');
};
