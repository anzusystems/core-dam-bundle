<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Model;

final class VideoShowEpisodeUrl
{
    private const API_VERSION = 1;

    public static function getOne(string $id): string
    {
        return sprintf('/api/adm/v%d/video-show-episode/%s', self::API_VERSION, $id);
    }

    public static function preparePayload(string $assetId, string $videoShowId): string
    {
        return sprintf('/api/adm/v%d/video-show-episode/asset/%s/video-show/%s/prepare-payload', self::API_VERSION, $assetId, $videoShowId);
    }

    public static function update(string $id): string
    {
        return sprintf('/api/adm/v%d/video-show-episode/%s', self::API_VERSION, $id);
    }

    public static function getListByShow(string $showId): string
    {
        return sprintf('/api/adm/v%d/video-show-episode/video-show/%s', self::API_VERSION, $showId);
    }

    public static function getListByAsset(string $assetId): string
    {
        return sprintf('/api/adm/v%d/video-show-episode/asset/%s', self::API_VERSION, $assetId);
    }

    public static function createPath(): string
    {
        return sprintf('/api/adm/v%d/video-show-episode', self::API_VERSION);
    }
}
