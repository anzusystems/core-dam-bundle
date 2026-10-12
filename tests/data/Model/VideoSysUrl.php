<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Model;

final class VideoSysUrl
{
    private const int API_VERSION = 1;

    public static function createFromUrl(): string
    {
        return sprintf('/api/sys/v%d/video/from-url', self::API_VERSION);
    }
}
