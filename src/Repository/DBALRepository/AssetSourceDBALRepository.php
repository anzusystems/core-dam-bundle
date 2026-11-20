<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\DBALRepository;

use AnzuSystems\CoreDamBundle\Model\Enum\AssetStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\AbstractAnzuDBALRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;

final class AssetSourceDBALRepository extends AbstractAnzuDBALRepository
{
    public const string TABLE_NAME = 'asset_source';

    public function getTableName(): string
    {
        return self::TABLE_NAME;
    }

    /**
     * @return array<string, int> image count by status
     */
    public function countImagesWithoutSource(int $extSystemId): array
    {
        return array_map(intval(...), $this->connection->fetchAllKeyValue(
            'SELECT asset.attributes_status, COUNT(*) FROM asset
            WHERE asset.ext_system_id = :extSystemId AND asset.attributes_asset_type = :assetType
            AND NOT EXISTS (SELECT 1 FROM ' . self::TABLE_NAME . ' link WHERE link.asset_id = asset.id)
            GROUP BY asset.attributes_status',
            [
                'extSystemId' => $extSystemId,
                'assetType' => AssetType::Image->value,
            ],
        ));
    }

    /**
     * @return list<string>
     */
    public function findImageIdsWithFileWithoutSource(int $extSystemId, string $fromId, int $limit): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT asset.id FROM asset
            WHERE asset.ext_system_id = :extSystemId AND asset.attributes_asset_type = :assetType
            AND asset.attributes_status = :status AND asset.id > :fromId
            AND NOT EXISTS (SELECT 1 FROM ' . self::TABLE_NAME . ' link WHERE link.asset_id = asset.id)
            ORDER BY asset.id LIMIT :limit',
            [
                'extSystemId' => $extSystemId,
                'assetType' => AssetType::Image->value,
                'status' => AssetStatus::WithFile->value,
                'fromId' => $fromId,
                'limit' => $limit,
            ],
            ['limit' => ParameterType::INTEGER],
        );
    }

    /**
     * @param list<string> $assetIds
     *
     * @return int links added
     */
    public function linkSource(array $assetIds, string $sourceId): int
    {
        return (int) $this->connection->executeStatement(
            'INSERT IGNORE INTO ' . self::TABLE_NAME . ' (asset_id, author_id) SELECT asset.id, :sourceId FROM asset WHERE asset.id IN (:assetIds)
            AND NOT EXISTS (SELECT 1 FROM ' . self::TABLE_NAME . ' link WHERE link.asset_id = asset.id)',
            ['sourceId' => $sourceId, 'assetIds' => $assetIds],
            ['assetIds' => ArrayParameterType::STRING],
        );
    }
}
