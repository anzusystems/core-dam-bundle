<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $configurator): void {
    $configurator
        ->parameters()
        ->set(
            'anzu_systems.dam_bundle.index_asset',
            [
                'id' => [
                    'type' => 'keyword',
                ],
                'mainFileId' => [
                    'type' => 'keyword',
                ],
                'type' => [
                    'type' => 'keyword',
                ],
                'podcastIds' => [
                    'type' => 'text',
                    'fields' => [
                        'podcastId' => [
                            'type' => 'keyword',
                        ],
                    ],
                ],
                'keywordIds' => [
                    'type' => 'text',
                    'fields' => [
                        'keywordId' => [
                            'type' => 'keyword',
                        ],
                    ],
                ],
                'fileIds' => [
                    'type' => 'keyword',
                ],
                'withProcessedFile' => [
                    'type' => 'boolean',
                ],
                'described' => [
                    'type' => 'boolean',
                ],
                'visible' => [
                    'type' => 'boolean',
                ],
                'generatedBySystem' => [
                    'type' => 'boolean',
                ],
                'inPodcast' => [
                    'type' => 'boolean',
                ],
                'createdAt' => [
                    'type' => 'date',
                ],
                'modifiedAt' => [
                    'type' => 'date',
                ],
                'title' => [
                    'type' => 'text',
                    'analyzer' => 'exact_stop',
                    'fields' => [
                        'lang' => [
                            'type' => 'text',
                            'analyzer' => 'lang',
                        ],
                    ],
                ],
                'originFileName' => [
                    'type' => 'text',
                    'analyzer' => 'exact',
                    'fields' => [
                        'edgegrams' => [
                            'type' => 'text',
                            'analyzer' => 'edgegrams',
                        ],
                    ],
                ],
                'mimeType' => [
                    'type' => 'keyword',
                ],
                'size' => [
                    'type' => 'long',
                ],
                'ratioWidth' => [
                    'type' => 'integer',
                ],
                'ratioHeight' => [
                    'type' => 'integer',
                ],
                'width' => [
                    'type' => 'integer',
                ],
                'height' => [
                    'type' => 'integer',
                ],
                'rotation' => [
                    'type' => 'integer',
                ],
                'mostDominantColor' => [
                    'type' => 'keyword',
                ],
                'closestMostDominantColor' => [
                    'type' => 'keyword',
                ],
                'pixelSize' => [
                    'type' => 'integer',
                ],
                'orientation' => [
                    'type' => 'keyword',
                ],
                'pageCount' => [
                    'type' => 'integer',
                ],
                'duration' => [
                    'type' => 'integer',
                ],
                'bitrate' => [
                    'type' => 'integer',
                ],
                'slotsCount' => [
                    'type' => 'integer',
                ],
                'codecName' => [
                    'type' => 'keyword',
                ],
                'distributedInServices' => [
                    'type' => 'keyword',
                ],
                'slotNames' => [
                    'type' => 'keyword',
                ],
                'fromRss' => [
                    'type' => 'boolean',
                ],
            ]
        );
};
