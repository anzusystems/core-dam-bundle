<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Model;

final class AssetFileSysUrl
{
    private const int API_VERSION = 1;

    public static function create(): string
    {
        return sprintf('/api/sys/v%d/asset-file', self::API_VERSION);
    }

    public static function createFromUrl(): string
    {
        return sprintf('/api/sys/v%d/asset-file/from-url', self::API_VERSION);
    }
}
