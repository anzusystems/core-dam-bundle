<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\CustomFilter;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\ApiFilter\CustomFilterInterface;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use Doctrine\ORM\QueryBuilder;

/**
 * Scopes a {@see \AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest} listing to one ext system. The
 * request stores its ext system as a denormalised scalar column ({@see
 * \AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest::$extSystemId}), so it filters on `t.extSystemId`.
 */
final class TtsNarrationRequestExtSystemFilter implements CustomFilterInterface
{
    public const string EXT_SYSTEM = 'extSystem';

    /**
     * Sets this filter's value on the given api params (keeps the wiring out of controllers).
     */
    public static function applyTo(ApiParams $apiParams, ExtSystem $extSystem): ApiParams
    {
        $filter = $apiParams->getFilter();
        $filter[ApiParams::FILTER_CUSTOM][self::EXT_SYSTEM] = (string) $extSystem->getId();
        $apiParams->setFilter($filter);

        return $apiParams;
    }

    public function apply(QueryBuilder $dqb, string $field, string | int $value): QueryBuilder
    {
        if (self::EXT_SYSTEM === $field) {
            $dqb->andWhere('t.extSystemId = :extSystemId')
                ->setParameter('extSystemId', $value);
        }

        return $dqb;
    }
}
