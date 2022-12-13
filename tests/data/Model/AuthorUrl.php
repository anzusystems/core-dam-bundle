<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Model;

final class AuthorUrl
{
    private const API_VERSION = 1;

    public static function getOne(string $id): string
    {
        return sprintf('/api/adm/v%d/author/%s', self::API_VERSION, $id);
    }

    public static function update(string $id): string
    {
        return sprintf('/api/adm/v%d/author/%s', self::API_VERSION, $id);
    }

    public static function searchByExtSystem(int $extSystemId): string
    {
        return sprintf('/api/adm/v%d/author/ext-system/%d/search', self::API_VERSION, $extSystemId);
    }

    public static function createPath(): string
    {
        return sprintf('/api/adm/v%d/author', self::API_VERSION);
    }
}
