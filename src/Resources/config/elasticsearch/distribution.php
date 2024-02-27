<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $configurator): void {
    $configurator
        ->parameters()
        ->set(
            'anzu_systems.dam_bundle.index_distribution',
            [
                'id' => [
                    'type' => 'keyword',
                ],
                'extId' => [
                    'type' => 'keyword',
                ],
                'service' => [
                    'type' => 'keyword',
                ],
                'serviceSlug' => [
                    'type' => 'keyword',
                ],
                'status' => [
                    'type' => 'keyword',
                ],
                'assetId' => [
                    'type' => 'keyword',
                ],
                'assetFileId' => [
                    'type' => 'keyword',
                ],
                'licenceId' => [
                    'type' => 'keyword',
                ],
                'createdAt' => [
                    'type' => 'date',
                ],
            ]
        );
};
