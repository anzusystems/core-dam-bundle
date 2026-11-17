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

    public function countByAuthor(string $authorId): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . self::TABLE_NAME . ' entity WHERE entity.author_id = :authorId',
            ['authorId' => $authorId],
        );
    }

    /**
     * Gives every asset of the author also the target, as a source or as an author.
     *
     * @return int links added
     */
    public function linkAssetsOfAuthor(string $authorId, string $targetId, bool $source): int
    {
        return (int) $this->connection->executeStatement(
            'INSERT IGNORE INTO ' . ($source ? AssetSourceDBALRepository::TABLE_NAME : self::TABLE_NAME) . ' (asset_id, author_id)
            SELECT link.asset_id, :targetId FROM ' . self::TABLE_NAME . ' link WHERE link.author_id = :authorId
            UNION
            SELECT link.asset_id, :targetId FROM ' . AssetSourceDBALRepository::TABLE_NAME . ' link WHERE link.author_id = :authorId',
            [
                'authorId' => $authorId,
                'targetId' => $targetId,
            ],
        );
    }

    /**
     * Copies the asset links of a merged author, as an author and as a source, to `_author_merge_asset_author`, a
     * table the host creates outside of Doctrine, so the job that moves its assets to the author it goes under can be
     * traced and undone: `main_author_linked` tells whether the asset already had that author before the merge.
     *
     * @return int copied links
     */
    public function copyToMergeSnapshot(string $runId, string $authorId, string $mainAuthorId): int
    {
        $mainAuthorLinked = '(EXISTS (SELECT 1 FROM ' . self::TABLE_NAME . ' linked WHERE linked.asset_id = link.asset_id AND linked.author_id = :mainAuthorId)
            OR EXISTS (SELECT 1 FROM ' . AssetSourceDBALRepository::TABLE_NAME . ' linked WHERE linked.asset_id = link.asset_id AND linked.author_id = :mainAuthorId))';

        return (int) $this->connection->executeStatement(
            'INSERT INTO _author_merge_asset_author (run_id, asset_id, author_id, main_author_id, source, main_author_linked)
            SELECT :runId, link.asset_id, link.author_id, :mainAuthorId, 0, ' . $mainAuthorLinked . ' FROM ' . self::TABLE_NAME . ' link
            WHERE link.author_id = :authorId
            UNION ALL
            SELECT :runId, link.asset_id, link.author_id, :mainAuthorId, 1, ' . $mainAuthorLinked . ' FROM ' . AssetSourceDBALRepository::TABLE_NAME . ' link
            WHERE link.author_id = :authorId',
            [
                'runId' => $runId,
                'authorId' => $authorId,
                'mainAuthorId' => $mainAuthorId,
            ],
        );
    }
}
