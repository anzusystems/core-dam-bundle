<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Model;

final class PodcastEpisodeUrl
{
    private const API_VERSION = 1;

    public static function getOne(string $id): string
    {
        return sprintf('/api/adm/v%d/podcast-episode/%s', self::API_VERSION, $id);
    }

    public static function update(string $id): string
    {
        return sprintf('/api/adm/v%d/podcast-episode/%s', self::API_VERSION, $id);
    }

    public static function getListByPodcast(string $podcastId): string
    {
        return sprintf('/api/adm/v%d/podcast-episode/podcast/%s', self::API_VERSION, $podcastId);
    }

    public static function getListByAsset(string $assetId): string
    {
        return sprintf('/api/adm/v%d/podcast-episode/asset/%s', self::API_VERSION, $assetId);
    }

    public static function createPath(): string
    {
        return sprintf('/api/adm/v%d/podcast-episode', self::API_VERSION);
    }
}
