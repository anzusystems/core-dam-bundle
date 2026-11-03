<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Model;

final class ImageSysUrl
{
    private const int API_VERSION = 1;

    public static function use(): string
    {
        return sprintf('/api/sys/v%d/image/use', self::API_VERSION);
    }

    public static function release(): string
    {
        return sprintf('/api/sys/v%d/image/release', self::API_VERSION);
    }

    public static function transfer(): string
    {
        return sprintf('/api/sys/v%d/image/transfer', self::API_VERSION);
    }

    public static function copyJob(): string
    {
        return sprintf('/api/sys/v%d/image/copy-job', self::API_VERSION);
    }
}
