<?php

declare(strict_types=1);

namespace Symfony\Component\Routing\Loader\Configurator;

return static function (RoutingConfigurator $routes): void {
    $routes
        ->import('@AnzuSystemsCoreDamBundle/Controller/Api/Adm/V1', type: 'annotation')
        ->prefix('/api/adm/v1/');

    $routes
        ->import('@AnzuSystemsCoreDamBundle/Controller/Api/Pub/V1', type: 'annotation')
        ->prefix('/api/pub/v1/');

    $routes
        ->import('@AnzuSystemsCoreDamBundle/Controller/ImageController.php', type: 'annotation')
        ->prefix('/');

    $routes
        ->import('@AnzuSystemsCoreDamBundle/Controller/Adm', type: 'attribute')
        ->prefix('/adm/');
};
