<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\CustomFilter;

use AnzuSystems\CommonBundle\ApiFilter\CustomFilterInterface;
use Doctrine\ORM\QueryBuilder;

final class CustomAssetTypeFilter implements CustomFilterInterface
{
    public const string ASSET_TYPE = 'assetType';

    public function apply(QueryBuilder $dqb, string $field, string | int $value): QueryBuilder
    {
        if (self::ASSET_TYPE === $field) {
            $dqb->andWhere('t.type = :assetType')
                ->setParameter('assetType', $value);
        }

        return $dqb;
    }
}
