<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\Repository;

use AnzuSystems\CoreDamBundle\Elasticsearch\RebuildIndexConfig;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface DBALIndexableRepositoryInterface
{
    /**
     * @return array{
     *     array{
     *          id: string
     *      }
     * }
     */
    public function getAllForIndexRebuild(RebuildIndexConfig $config): array;

    public function getAllCountForIndexRebuild(RebuildIndexConfig $config): int;
}
