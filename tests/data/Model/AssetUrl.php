<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Model;

use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures;

final class AssetUrl
{
    private const API_VERSION = 1;
    private const LICENCE_ID = AssetLicenceFixtures::DEFAULT_LICENCE_ID;

    public static function createPath(): string
    {
        return sprintf(
            '/api/adm/v%d/asset/licence/%d',
            self::API_VERSION,
            self::LICENCE_ID
        );
    }

    /**
     * @param list<int> $licenceIds
     */
    public static function licenceSearch(array $licenceIds): string
    {
        return sprintf(
            '/api/adm/v%d/asset/licence/search?licences=%s',
            self::API_VERSION,
            implode(',', $licenceIds)
        );
    }
}
