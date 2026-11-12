<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\DBALRepository;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Model\Dto\Author\AuthorMergeCandidateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Author\AuthorNameDto;
use AnzuSystems\CoreDamBundle\Repository\AbstractAnzuDBALRepository;
use Doctrine\Common\Collections\Order;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Types;

final class AuthorDBALRepository extends AbstractAnzuDBALRepository
{
    /**
     * Length of `_author_merge_log.group_name` in the host migration.
     */
    public const int MERGE_LOG_GROUP_NAME_LENGTH = 64;

    private const string TABLE_NAME = 'author';
    private const int ID_CHUNK_SIZE = 1_000;

    public function getTableName(): string
    {
        return self::TABLE_NAME;
    }

    public function countForNormalization(bool $all): int
    {
        return (int) $this->createNormalizationQueryBuilder($all)
            ->select('COUNT(author.id)')
            ->fetchOne();
    }

    /**
     * @return list<AuthorNameDto>
     */
    public function findForNormalization(string $lastId, int $limit, bool $all): array
    {
        $rows = $this->createNormalizationQueryBuilder($all)
            ->select('author.id, author.name, author.name_normalized')
            ->andWhere('author.id > :lastId')
            ->setParameter('lastId', $lastId)
            ->orderBy('author.id', Order::Ascending->value)
            ->setMaxResults($limit)
            ->fetchAllAssociative();

        return array_map(
            static fn (array $row): AuthorNameDto => new AuthorNameDto(
                id: (string) $row['id'],
                name: (string) $row['name'],
                nameNormalized: (string) $row['name_normalized'],
            ),
            $rows,
        );
    }

    public function updateNameNormalized(string $id, string $nameNormalized): void
    {
        $this->connection->update(self::TABLE_NAME, ['name_normalized' => $nameNormalized], ['id' => $id]);
    }

    /**
     * Authors of the ext system that share their normalized name with another one, or are used (at least
     * $minAssets assets or a child author), with what the merge plan needs.
     *
     * @return list<AuthorMergeCandidateDto>
     */
    public function findMergeCandidates(int $extSystemId, int $minAssets): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT author.id, author.name, author.name_normalized, author.identifier, author.flags_reviewed, author.created_at,
                creator.email,
                (SELECT COUNT(*) FROM asset_author WHERE asset_author.author_id = author.id) AS asset_count,
                (SELECT GROUP_CONCAT(hierarchy.author_target) FROM author_is_current_author hierarchy WHERE hierarchy.author_source = author.id) AS current_author_ids,
                (SELECT COUNT(*) FROM author_is_current_author hierarchy WHERE hierarchy.author_target = author.id) AS child_author_count,
                author.name_normalized IN (
                    SELECT shared.name_normalized FROM ' . self::TABLE_NAME . ' shared
                    WHERE shared.ext_system_id = :extSystemId AND shared.name_normalized <> :empty
                    GROUP BY shared.name_normalized HAVING COUNT(*) > 1
                ) AS shared_name
            FROM ' . self::TABLE_NAME . ' author
            LEFT JOIN user creator ON creator.id = author.created_by_id
            WHERE author.ext_system_id = :extSystemId
            HAVING shared_name OR asset_count >= :minAssets OR child_author_count > 0',
            [
                'extSystemId' => $extSystemId,
                'empty' => App::EMPTY_STRING,
                'minAssets' => $minAssets,
            ],
        );

        return array_map(
            static fn (array $row): AuthorMergeCandidateDto => new AuthorMergeCandidateDto(
                id: (string) $row['id'],
                name: (string) $row['name'],
                nameNormalized: (string) $row['name_normalized'],
                identifier: (string) $row['identifier'],
                reviewed: (bool) $row['flags_reviewed'],
                assetCount: (int) $row['asset_count'],
                createdAt: (string) $row['created_at'],
                createdBy: (string) $row['email'],
                currentAuthorIds: null === $row['current_author_ids'] ? [] : explode(',', (string) $row['current_author_ids']),
                childAuthorCount: (int) $row['child_author_count'],
            ),
            $rows,
        );
    }

    /**
     * Logs a variant and the author it goes under as they are before the merge, in `_author_merge_log`, a table the
     * host creates outside of Doctrine.
     */
    public function insertMergeLog(string $runId, string $groupName, string $variantId, string $mainAuthorId): void
    {
        $this->connection->executeStatement(
            'INSERT INTO _author_merge_log (run_id, ext_system_id, group_name, variant_id, variant_name, variant_identifier,
                variant_reviewed, variant_can_be_current_author, main_author_id, main_author_name, main_author_reviewed, created_at)
            SELECT :runId, variant.ext_system_id, :groupName, variant.id, variant.name, variant.identifier,
                variant.flags_reviewed, variant.flags_can_be_current_author, main_author.id, main_author.name, main_author.flags_reviewed, :createdAt
            FROM ' . self::TABLE_NAME . ' variant
            INNER JOIN ' . self::TABLE_NAME . ' main_author ON main_author.id = :mainAuthorId
            WHERE variant.id = :variantId',
            [
                'runId' => $runId,
                'groupName' => $groupName,
                'variantId' => $variantId,
                'mainAuthorId' => $mainAuthorId,
                'createdAt' => App::getAppDate(),
            ],
            [
                'createdAt' => Types::DATETIME_IMMUTABLE,
            ],
        );
    }

    /**
     * @param list<string> $authorIds
     *
     * @return array<string, string> by author id, unknown ids left out
     */
    public function findNames(array $authorIds): array
    {
        return $this->fetchColumnByIds('name', $authorIds);
    }

    /**
     * @param list<string> $authorIds
     *
     * @return array<string, int> by author id, unknown ids left out
     */
    public function findExtSystemIds(array $authorIds): array
    {
        return array_map(intval(...), $this->fetchColumnByIds('ext_system_id', $authorIds));
    }

    /**
     * @param list<string> $authorIds
     *
     * @return array<string, string>
     */
    private function fetchColumnByIds(string $column, array $authorIds): array
    {
        $values = [];
        foreach (array_chunk($authorIds, self::ID_CHUNK_SIZE) as $chunk) {
            $rows = $this->connection->createQueryBuilder()
                ->select('author.id, author.' . $column)
                ->from(self::TABLE_NAME, 'author')
                ->where('author.id IN (:authorIds)')
                ->setParameter('authorIds', $chunk, ArrayParameterType::STRING)
                ->fetchAllKeyValue();
            foreach ($rows as $id => $value) {
                $values[(string) $id] = (string) $value;
            }
        }

        return $values;
    }

    private function createNormalizationQueryBuilder(bool $all): QueryBuilder
    {
        $qb = $this->connection->createQueryBuilder()->from(self::TABLE_NAME, 'author');
        if (false === $all) {
            $qb->where('author.name_normalized = :empty')->setParameter('empty', App::EMPTY_STRING);
        }

        return $qb;
    }
}
