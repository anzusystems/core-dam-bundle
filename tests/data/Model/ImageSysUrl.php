<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Model;

final class ImageSysUrl
{
    private const int API_VERSION = 1;

    public static function firstUse(): string
    {
        return sprintf('/api/sys/v%d/image/first-use', self::API_VERSION);
    }

    public static function usage(): string
    {
        return sprintf('/api/sys/v%d/image/usage', self::API_VERSION);
    }
}
