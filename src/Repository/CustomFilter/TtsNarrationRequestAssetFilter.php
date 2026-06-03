<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\CustomFilter;

use AnzuSystems\CommonBundle\ApiFilter\CustomFilterInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Scopes a {@see \AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest} listing to the requests targeting
 * one stable asset. The request stores its target asset as a denormalised scalar GUID column ({@see
 * \AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest::$assetId}), so it filters on `t.assetId`.
 */
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
