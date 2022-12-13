<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\CustomFilter;

use AnzuSystems\CommonBundle\ApiFilter\CustomFilterInterface;
use Doctrine\ORM\QueryBuilder;

final class CustomImageFilter implements CustomFilterInterface
{
    public const IMAGE = 'image';

    public function apply(QueryBuilder $dqb, string $field, string | int $value): QueryBuilder
    {
        if (self::IMAGE === $field) {
            $dqb->andWhere('IDENTITY(t.image) = :imageId')
                ->setParameter('imageId', $value);
        }

        return $dqb;
    }
}
