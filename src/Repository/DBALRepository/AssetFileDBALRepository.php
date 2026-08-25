<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\DBALRepository;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Repository\AbstractAnzuDBALRepository;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;

final class AssetFileDBALRepository extends AbstractAnzuDBALRepository
{
    private const string TABLE_NAME = 'asset_file';

    public function getTableName(): string
    {
        return self::TABLE_NAME;
    }

    /**
     * Write-once: the IS NULL condition lives in the SQL itself so the write stays atomic under
     * concurrent requests.
     *
     * @param array<string, DateTimeImmutable> $firstUsedAtByDamId
     */
    public function updateFirstUsedAtIfUnset(array $firstUsedAtByDamId): int
    {
        if ([] === $firstUsedAtByDamId) {
            return App::ZERO;
        }

        $caseWhen = [];
        $params = [];
        $types = [];
        $index = App::ZERO;

        foreach ($firstUsedAtByDamId as $damId => $firstUsedAt) {
            $idParam = 'damId' . $index;
            $valueParam = 'firstUsedAt' . $index;

            $caseWhen[] = sprintf('WHEN :%s THEN :%s', $idParam, $valueParam);
            $params[$idParam] = $damId;
            $params[$valueParam] = $firstUsedAt;
            $types[$valueParam] = Types::DATETIME_IMMUTABLE;
            ++$index;
        }

        $params['ids'] = array_keys($firstUsedAtByDamId);
        $types['ids'] = ArrayParameterType::STRING;
        $params['modifiedAt'] = App::getAppDate();
        $types['modifiedAt'] = Types::DATETIME_IMMUTABLE;

        return (int) $this->connection->executeStatement(
            sprintf(
                'UPDATE %s SET first_used_at = CASE id %s END, modified_at = :modifiedAt WHERE id IN (:ids) AND first_used_at IS NULL',
                self::TABLE_NAME,
                implode(' ', $caseWhen),
            ),
            $params,
            $types,
        );
    }
}
