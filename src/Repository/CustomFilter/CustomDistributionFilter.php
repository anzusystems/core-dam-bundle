<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\CustomFilter;

use AnzuSystems\CommonBundle\ApiFilter\CustomFilterInterface;
use Doctrine\ORM\QueryBuilder;

final class CustomDistributionFilter implements CustomFilterInterface
{
    public const string ASSET_ID = 'assetId';
    public const string ASSET_FILE_ID = 'assetFileId';

    public function apply(QueryBuilder $dqb, string $field, string | int $value): QueryBuilder
    {
        if (self::ASSET_ID === $field) {
            $dqb->andWhere('t.assetId = :assetId')
                ->setParameter('assetId', $value);
        }
        if (self::ASSET_FILE_ID === $field) {
            $dqb->andWhere('t.assetFileId = :assetFileId')
                ->setParameter('assetFileId', $value);
        }

        return $dqb;
    }
}
