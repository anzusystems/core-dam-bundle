<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Model;

final class ConfigurationUrl
{
    private const API_VERSION = 1;

    public static function getPub(): string
    {
        return sprintf('/api/pub/v%d/configuration', self::API_VERSION);
    }
}
