<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\DBALRepository;

use AnzuSystems\CoreDamBundle\Repository\AbstractAnzuDBALRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;

final class AssetFileDBALRepository extends AbstractAnzuDBALRepository
{
    private const string TABLE_NAME = 'asset_file';
    private const string FILE_IDS_OF_ASSETS = 'SELECT id FROM image_file WHERE asset_id IN (:assetIds)
        UNION ALL SELECT id FROM audio_file WHERE asset_id IN (:assetIds)
        UNION ALL SELECT id FROM video_file WHERE asset_id IN (:assetIds)
        UNION ALL SELECT id FROM document_file WHERE asset_id IN (:assetIds)';

    public function getTableName(): string
    {
        return self::TABLE_NAME;
    }

    public function countNotSingleUseByLicence(int $licenceId): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . self::TABLE_NAME . ' WHERE licence_id = :licenceId AND flags_single_use = 0',
            ['licenceId' => $licenceId],
        );
    }

    /**
     * `enforced` is set when the licence or a source of the asset enforces single use.
     *
     * @return list<array{id: string, enforced: int|string, exif_data: string|null}>
     */
    public function findNotSingleUseByLicence(int $licenceId, string $fromId, int $limit): array
    {
        /** @var list<array{id: string, enforced: int|string, exif_data: string|null}> */
        return $this->connection->fetchAllAssociative(
            'SELECT asf.id, metadata.exif_data, licence.flags_single_use_enforced OR EXISTS (
                SELECT 1 FROM asset_source link JOIN author source ON source.id = link.author_id
                WHERE link.asset_id = COALESCE(image.asset_id, audio.asset_id, video.asset_id, document.asset_id)
                AND source.flags_single_use_enforced = 1
            ) AS enforced
            FROM ' . self::TABLE_NAME . ' asf
            JOIN asset_licence licence ON licence.id = asf.licence_id
            LEFT JOIN asset_file_metadata metadata ON metadata.id = asf.metadata_id
            LEFT JOIN image_file image ON image.id = asf.id
            LEFT JOIN audio_file audio ON audio.id = asf.id
            LEFT JOIN video_file video ON video.id = asf.id
            LEFT JOIN document_file document ON document.id = asf.id
            WHERE asf.licence_id = :licenceId AND asf.flags_single_use = 0 AND asf.id > :fromId
            ORDER BY asf.id LIMIT :limit',
            ['licenceId' => $licenceId, 'fromId' => $fromId, 'limit' => $limit],
            ['limit' => ParameterType::INTEGER],
        );
    }

    /**
     * @param list<string> $assetIds
     */
    public function countNotSingleUseOfAssets(array $assetIds): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . self::TABLE_NAME . ' WHERE flags_single_use = 0 AND id IN (' . self::FILE_IDS_OF_ASSETS . ')',
            ['assetIds' => $assetIds],
            ['assetIds' => ArrayParameterType::STRING],
        );
    }

    /**
     * @param list<string> $assetIds
     *
     * @return int files switched
     */
    public function setSingleUseOfAssets(array $assetIds): int
    {
        return (int) $this->connection->executeStatement(
            'UPDATE ' . self::TABLE_NAME . ' SET flags_single_use = 1 WHERE flags_single_use = 0 AND id IN (' . self::FILE_IDS_OF_ASSETS . ')',
            ['assetIds' => $assetIds],
            ['assetIds' => ArrayParameterType::STRING],
        );
    }

    /**
     * @param list<string> $ids
     *
     * @return int files switched
     */
    public function setSingleUse(array $ids): int
    {
        return (int) $this->connection->executeStatement(
            'UPDATE ' . self::TABLE_NAME . ' SET flags_single_use = 1 WHERE flags_single_use = 0 AND id IN (:ids)',
            ['ids' => $ids],
            ['ids' => ArrayParameterType::STRING],
        );
    }
}
