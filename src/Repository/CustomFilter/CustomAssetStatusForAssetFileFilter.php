<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\CustomFilter;

use AnzuSystems\CommonBundle\ApiFilter\CustomFilterInterface;
use Doctrine\ORM\QueryBuilder;

final class CustomAssetStatusForAssetFileFilter implements CustomFilterInterface
{
    public const ASSET_STATUS = 'assetStatus';

    public function apply(QueryBuilder $dqb, string $field, string | int $value): QueryBuilder
    {
        if (self::ASSET_STATUS === $field) {
            $dqb->innerJoin('t.asset', 'asset')
                ->andWhere('asset.attributes.status = :assetStatus')
                ->setParameter('assetStatus', $value);
        }

        return $dqb;
    }
}
