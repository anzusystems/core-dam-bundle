<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\CustomFilter;

use AnzuSystems\CommonBundle\ApiFilter\CustomFilterInterface;
use Doctrine\ORM\QueryBuilder;

final class VideoShowEpisodeFilter implements CustomFilterInterface
{
    public const VIDEO_SHOW = 'videoShow';
    public const ASSET = 'asset';

    public function apply(QueryBuilder $dqb, string $field, string | int $value): QueryBuilder
    {
        if (self::VIDEO_SHOW === $field) {
            $dqb->andWhere('IDENTITY(t.videoShow) = :videoShowId')
                ->setParameter('videoShowId', $value);
        }
        if (self::ASSET === $field) {
            $dqb->andWhere('IDENTITY(t.asset) = :assetId')
                ->setParameter('assetId', $value);
        }

        return $dqb;
    }
}
