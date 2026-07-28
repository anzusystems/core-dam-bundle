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
                'canBeCurrentAuthor' => [
                    'type' => 'boolean',
                ],
                'name' => [
                    'type' => 'text',
                    'analyzer' => 'author_exact_stop',
                    'fields' => [
                        'edgegrams' => [
                            'type' => 'text',
                            'analyzer' => 'author_edgegrams',
                            'search_analyzer' => 'author_exact_stop',
                        ],
                    ],
                ],
                'type' => [
                    'type' => 'keyword',
                ],
                'createdAt' => [
                    'type' => 'date',
                    'format' => 'epoch_second',
                ],
            ]
        );
};
