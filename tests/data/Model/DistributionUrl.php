<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Model;

final class DistributionUrl
{
    private const int API_VERSION = 1;

    /**
     * @param list<int> $licenceIds
     */
    public static function search(array $licenceIds): string
    {
        return sprintf(
            '/api/adm/v%d/distribution/search?licences=%s',
            self::API_VERSION,
            implode(',', $licenceIds)
        );
    }
}
