<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Model;

final class TtsNarrationRequestSysUrl
{
    private const int API_VERSION = 1;

    public static function dispatch(): string
    {
        return sprintf('/api/sys/v%d/audio/narration', self::API_VERSION);
    }
}
