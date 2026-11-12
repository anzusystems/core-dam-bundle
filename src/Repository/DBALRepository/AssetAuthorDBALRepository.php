<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\DBALRepository;

use AnzuSystems\CoreDamBundle\Repository\AbstractAnzuDBALRepository;
use Doctrine\DBAL\ArrayParameterType;

final class AssetAuthorDBALRepository extends AbstractAnzuDBALRepository
{
    private const string TABLE_NAME = 'asset_author';

    public function getTableName(): string
    {
        return self::TABLE_NAME;
    }

    /**
     * @param string[] $assetIds
     *
     * @return array<string, array{ids: string[], names: string[]}>
     */
    public function getByAsset(array $assetIds): array
    {
        $qb = $this->connection->createQueryBuilder();
        /** @noinspection PhpDqlBuilderUnknownModelInspection */
        $qb
            ->select('entity.author_id, entity.asset_id, author.name')
            ->from(self::TABLE_NAME, 'entity')
            ->leftJoin('entity', 'author', 'author', 'author.id = entity.author_id')
            ->where('entity.asset_id IN (:assetIds)')
            ->setParameter('assetIds', $assetIds, ArrayParameterType::STRING)
        ;

        $res = $qb->fetchAllAssociative();

        $data = [];
        foreach ($res as $item) {
            if (false === array_key_exists($item['asset_id'], $data)) {
                $data[$item['asset_id']]['ids'] = [];
                $data[$item['asset_id']]['names'] = [];
            }

            $data[$item['asset_id']]['ids'][] = $item['author_id'];
            $data[$item['asset_id']]['names'][] = $item['name'];
        }

        return $data;
    }

    /**
     * Copies the asset links of a merged author to `_author_merge_asset_author`, a table the host creates outside of
     * Doctrine, so the job that moves its assets to the author it goes under can be traced.
     *
     * @return int copied links
     */
    public function copyToMergeSnapshot(string $runId, string $authorId, string $mainAuthorId): int
    {
        return (int) $this->connection->executeStatement(
            'INSERT INTO _author_merge_asset_author (run_id, asset_id, author_id, main_author_id)
            SELECT :runId, entity.asset_id, entity.author_id, :mainAuthorId FROM ' . self::TABLE_NAME . ' entity
            WHERE entity.author_id = :authorId',
            [
                'runId' => $runId,
                'authorId' => $authorId,
                'mainAuthorId' => $mainAuthorId,
            ],
        );
    }
}
