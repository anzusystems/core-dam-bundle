<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $configurator): void {
    $configurator
        ->parameters()
        ->set(
            'anzu_systems.dam_bundle.index_keyword',
            [
                'id' => [
                    'type' => 'keyword',
                ],
                'reviewed' => [
                    'type' => 'boolean',
                ],
                'name' => [
                    'type' => 'search_as_you_type',
                ],
            ]
        );
};
