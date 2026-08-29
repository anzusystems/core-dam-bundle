<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Model;

final class AssetListViewUrl
{
    private const int API_VERSION = 1;

    public static function getOne(int $id): string
    {
        return sprintf('/api/adm/v%d/asset-list-view/%d', self::API_VERSION, $id);
    }

    public static function update(int $id): string
    {
        return sprintf('/api/adm/v%d/asset-list-view/%d', self::API_VERSION, $id);
    }

    public static function delete(int $id): string
    {
        return sprintf('/api/adm/v%d/asset-list-view/%d', self::API_VERSION, $id);
    }

    public static function getList(): string
    {
        return sprintf('/api/adm/v%d/asset-list-view', self::API_VERSION);
    }

    public static function createPath(): string
    {
        return sprintf('/api/adm/v%d/asset-list-view', self::API_VERSION);
    }
}
