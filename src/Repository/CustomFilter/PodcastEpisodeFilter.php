<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\CustomFilter;

use AnzuSystems\CommonBundle\ApiFilter\CustomFilterInterface;
use Doctrine\ORM\QueryBuilder;

final class PodcastEpisodeFilter implements CustomFilterInterface
{
    public const string PODCAST = 'podcast';
    public const string ASSET = 'asset';

    public function apply(QueryBuilder $dqb, string $field, string | int $value): QueryBuilder
    {
        if (self::PODCAST === $field) {
            $dqb->andWhere('IDENTITY(t.podcast) = :podcastId')
                ->setParameter('podcastId', $value);
        }
        if (self::ASSET === $field) {
            $dqb->andWhere('IDENTITY(t.asset) = :assetId')
                ->setParameter('assetId', $value);
        }

        return $dqb;
    }
}
