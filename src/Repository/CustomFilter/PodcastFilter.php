<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\CustomFilter;

use AnzuSystems\CommonBundle\ApiFilter\CustomFilterInterface;
use Doctrine\ORM\QueryBuilder;

final class PodcastFilter implements CustomFilterInterface
{
    public const EXT_SYSTEM = 'extSystem';
    public const LICENCE = 'licence';

    public function apply(QueryBuilder $dqb, string $field, string | int $value): QueryBuilder
    {
        if (self::EXT_SYSTEM === $field) {
            $dqb
                ->innerJoin('t.licence', 'licence')
                ->andWhere('IDENTITY(licence.extSystem) = :extSystemId')
                ->setParameter('extSystemId', $value);
        }
        if (self::LICENCE === $field) {
            $dqb
                ->andWhere('IDENTITY(t.licence) = :licenceId')
                ->setParameter('licenceId', $value);
        }

        return $dqb;
    }
}
