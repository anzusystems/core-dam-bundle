<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Model;

final class PodcastUrl
{
    private const API_VERSION = 1;

    public static function getOne(string $id): string
    {
        return sprintf('/api/adm/v%d/podcast/%s', self::API_VERSION, $id);
    }

    public static function update(string $id): string
    {
        return sprintf('/api/adm/v%d/podcast/%s', self::API_VERSION, $id);
    }

    public static function getListByExtSystem(int $extSystemId): string
    {
        return sprintf('/api/adm/v%d/podcast/ext-system/%d', self::API_VERSION, $extSystemId);
    }

    public static function getListByLicence(int $licenceId): string
    {
        return sprintf('/api/adm/v%d/podcast/licence/%d', self::API_VERSION, $licenceId);
    }

    public static function createPath(): string
    {
        return sprintf('/api/adm/v%d/podcast', self::API_VERSION);
    }
}
