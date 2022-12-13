<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\CustomFilter;

use AnzuSystems\CommonBundle\ApiFilter\CustomFilterInterface;
use Doctrine\ORM\QueryBuilder;

final class CustomExtSystemFilter implements CustomFilterInterface
{
    public const EXT_SYSTEM = 'extSystem';

    public function apply(QueryBuilder $dqb, string $field, string | int $value): QueryBuilder
    {
        if (self::EXT_SYSTEM === $field) {
            $dqb->andWhere('IDENTITY(t.extSystem) = :extSystemId')
                ->setParameter('extSystemId', $value);
        }

        return $dqb;
    }
}
