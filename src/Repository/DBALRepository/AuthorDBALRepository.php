<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\DBALRepository;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Model\Dto\Author\AuthorNameDto;
use AnzuSystems\CoreDamBundle\Repository\AbstractAnzuDBALRepository;
use Doctrine\Common\Collections\Order;
use Doctrine\DBAL\Query\QueryBuilder;

final class AuthorDBALRepository extends AbstractAnzuDBALRepository
{
    private const string TABLE_NAME = 'author';

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

    private function createNormalizationQueryBuilder(bool $all): QueryBuilder
    {
        $qb = $this->connection->createQueryBuilder()->from(self::TABLE_NAME, 'author');
        if (false === $all) {
            $qb->where('author.name_normalized = :empty')->setParameter('empty', App::EMPTY_STRING);
        }

        return $qb;
    }
}
