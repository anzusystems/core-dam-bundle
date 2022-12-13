<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Model;

final class DistributionCategorySelectUrl
{
    private const API_VERSION = 1;

    public static function getOne(string $id): string
    {
        return sprintf('/api/adm/v%d/distribution/category-select/%s', self::API_VERSION, $id);
    }

    public static function update(string $id): string
    {
        return sprintf('/api/adm/v%d/distribution/category-select/%s', self::API_VERSION, $id);
    }

    public static function getList(int $extSystemId): string
    {
        return sprintf('/api/adm/v%d/distribution/category-select/ext-system/%d', self::API_VERSION, $extSystemId);
    }
}
