<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Model;

final class ExtSystemUrl
{
    private const API_VERSION = 1;

    public static function getOne(int $id): string
    {
        return sprintf('/api/adm/v%d/ext-system/%d', self::API_VERSION, $id);
    }

    public static function update(int $id): string
    {
        return sprintf('/api/adm/v%d/ext-system/%d', self::API_VERSION, $id);
    }

    public static function getList(): string
    {
        return sprintf('/api/adm/v%d/ext-system', self::API_VERSION);
    }
}
