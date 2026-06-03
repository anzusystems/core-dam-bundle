<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\CustomFilter;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\ApiFilter\CustomFilterInterface;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use Doctrine\ORM\QueryBuilder;

/**
 * Scopes a {@see \AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest} listing to the requests targeting
 * one stable asset. The request stores its target asset as a denormalised scalar GUID column ({@see
 * \AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest::$assetId}), so it filters on `t.assetId`.
 */
final class TtsNarrationRequestAssetFilter implements CustomFilterInterface
{
    public const string ASSET = 'asset';

    /**
     * Sets this filter's value on the given api params (keeps the wiring out of controllers).
     */
    public static function applyTo(ApiParams $apiParams, Asset $asset): ApiParams
    {
        $filter = $apiParams->getFilter();
        $filter[ApiParams::FILTER_CUSTOM][self::ASSET] = (string) $asset->getId();
        $apiParams->setFilter($filter);

        return $apiParams;
    }

    public function apply(QueryBuilder $dqb, string $field, string | int $value): QueryBuilder
    {
        if (self::ASSET === $field) {
            $dqb->andWhere('t.assetId = :assetId')
                ->setParameter('assetId', $value);
        }

        return $dqb;
    }
}
