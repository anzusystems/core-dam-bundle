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

    public function getByAsset(array $assetIds): array
    {
        $qb = $this->connection->createQueryBuilder();
        /** @noinspection PhpDqlBuilderUnknownModelInspection */
        $qb
            ->select('entity.author_id, entity.asset_id')
            ->from(self::TABLE_NAME, 'entity')
            ->where('entity.asset_id IN (:assetIds)')
            ->setParameter('assetIds', $assetIds, ArrayParameterType::STRING)
        ;

        $res = $qb->fetchAllAssociative();

        $data = [];
        foreach ($res as $item) {
            if (false === array_key_exists($item['asset_id'], $data)) {
                $data[$item['asset_id']]['ids'] = [];
            }

            $data[$item['asset_id']]['ids'][] = $item['author_id'];
        }

        return $data;
    }
}
