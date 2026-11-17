<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\DBALRepository;

use AnzuSystems\CoreDamBundle\Repository\AbstractAnzuDBALRepository;

final class AssetSourceDBALRepository extends AbstractAnzuDBALRepository
{
    public const string TABLE_NAME = 'asset_source';

    public function getTableName(): string
    {
        return self::TABLE_NAME;
    }
}
