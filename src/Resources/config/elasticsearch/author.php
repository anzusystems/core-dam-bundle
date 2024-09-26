<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $configurator): void {
    $configurator
        ->parameters()
        ->set(
            'anzu_systems.dam_bundle.index_author',
            [
                'id' => [
                    'type' => 'keyword',
                ],
                'identifier' => [
                    'type' => 'keyword',
                ],
                'reviewed' => [
                    'type' => 'boolean',
                ],
                'name' => [
                    'type' => 'text',
                    'analyzer' => 'exact_stop',
                    'fields' => [
                        'edgegrams' => [
                            'type' => 'text',
                            'analyzer' => 'edgegrams',
                        ],
                    ],
                ],
                'type' => [
                    'type' => 'keyword',
                ],
                'createdAt' => [
                    'type' => 'date',
                ],
            ]
        );
};
