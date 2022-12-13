<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\CustomFilter;

use AnzuSystems\CommonBundle\ApiFilter\CustomFilterInterface;
use Doctrine\ORM\QueryBuilder;

final class CustomFormElementsFilter implements CustomFilterInterface
{
    public const FORM = 'form';

    public function apply(QueryBuilder $dqb, string $field, string | int $value): QueryBuilder
    {
        if (self::FORM === $field) {
            $dqb->andWhere('IDENTITY(t.form) = :formId')
                ->setParameter('formId', $value);
        }

        return $dqb;
    }
}
