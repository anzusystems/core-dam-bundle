<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\CustomFilter;

use AnzuSystems\CommonBundle\ApiFilter\CustomFilterInterface;
use Doctrine\ORM\QueryBuilder;

/** Filters TtsNarrationRequest listing by denormalised extSystemId scalar column. */
final class TtsNarrationRequestExtSystemFilter implements CustomFilterInterface
{
    public const string EXT_SYSTEM = 'extSystem';

    public function apply(QueryBuilder $dqb, string $field, string | int $value): QueryBuilder
    {
        if (self::EXT_SYSTEM === $field) {
            $dqb->andWhere('t.extSystemId = :extSystemId')
                ->setParameter('extSystemId', $value);
        }

        return $dqb;
    }
}
