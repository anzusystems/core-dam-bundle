<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\DBALRepository;

use AnzuSystems\CoreDamBundle\Repository\AbstractAnzuDBALRepository;

final class AssetPodcastDBALRepository extends AbstractAnzuDBALRepository
{
    private const string TABLE_NAME = 'podcast_episode';

    public function getTableName(): string
    {
        return self::TABLE_NAME;
    }

    public function getByAsset(string $assetId): array
    {
        $qb = $this->connection->createQueryBuilder();
        /** @noinspection PhpDqlBuilderUnknownModelInspection */
        $qb
            ->select('distinct entity.podcast_id')
            ->from(self::TABLE_NAME, 'entity')
            ->where('entity.asset_id = :assetId')
            ->setParameter('assetId', $assetId)
        ;

        return $qb->fetchFirstColumn();
    }
}
