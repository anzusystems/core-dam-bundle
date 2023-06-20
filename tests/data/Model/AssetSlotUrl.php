<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Model;

final class AssetSlotUrl
{
    private const API_VERSION = 1;

    public static function getList(string $assetId): string
    {
        return sprintf('/api/adm/v%d/asset-slot/asset/%s', self::API_VERSION, $assetId);
    }

    public static function update(string $assetId): string
    {
        return sprintf('/api/adm/v%d/asset-slot/asset/%s', self::API_VERSION, $assetId);
    }
}
