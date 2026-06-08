<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\CustomFilter;

use AnzuSystems\CommonBundle\ApiFilter\CustomFilterInterface;
use Doctrine\ORM\QueryBuilder;

/** Filters TtsNarrationRequest listing by denormalised assetId scalar column. */
final class TtsNarrationRequestAssetFilter implements CustomFilterInterface
{
    public const string ASSET = 'asset';

    public function apply(QueryBuilder $dqb, string $field, string | int $value): QueryBuilder
    {
        if (self::ASSET === $field) {
            $dqb->andWhere('t.assetId = :assetId')
                ->setParameter('assetId', $value);
        }

        return $dqb;
    }
}
