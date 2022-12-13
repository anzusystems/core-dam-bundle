<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes
        ->import(resource: __DIR__ . '/../../../Controller/Api/Adm/V1', type: 'attribute')
        ->prefix('/api/adm/v1/');

    $routes
        ->import(resource: __DIR__ . '/../../../Controller/ImageController.php', type: 'attribute')
        ->prefix('/');
};
