<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Model;

final class TtsAssetUrl
{
    private const int API_VERSION = 1;

    public static function getOne(string $assetId): string
    {
        return sprintf('/api/adm/v%d/tts-asset/%s', self::API_VERSION, $assetId);
    }

    public static function regenerate(string $assetId): string
    {
        return sprintf('/api/adm/v%d/tts-asset/%s/regenerate', self::API_VERSION, $assetId);
    }

    public static function unpublish(string $assetId): string
    {
        return sprintf('/api/adm/v%d/tts-asset/%s', self::API_VERSION, $assetId);
    }
}
