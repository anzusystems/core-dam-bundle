<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Model;

final class VideoShowUrl
{
    private const API_VERSION = 1;

    public static function getOne(string $id): string
    {
        return sprintf('/api/adm/v%d/video-show/%s', self::API_VERSION, $id);
    }

    public static function update(string $id): string
    {
        return sprintf('/api/adm/v%d/video-show/%s', self::API_VERSION, $id);
    }

    public static function getListByExtSystem(int $extSystemId): string
    {
        return sprintf('/api/adm/v%d/video-show/ext-system/%d', self::API_VERSION, $extSystemId);
    }

    public static function getListByLicence(int $licenceId): string
    {
        return sprintf('/api/adm/v%d/video-show/licence/%d', self::API_VERSION, $licenceId);
    }

    public static function createPath(): string
    {
        return sprintf('/api/adm/v%d/video-show', self::API_VERSION);
    }
}
