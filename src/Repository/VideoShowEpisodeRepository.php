<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\VideoShow;
use AnzuSystems\CoreDamBundle\Entity\VideoShowEpisode;

/**
 * @extends AbstractAnzuRepository<VideoShowEpisode>
 *
 * @method VideoShowEpisode|null find($id, $lockMode = null, $lockVersion = null)
 * @method VideoShowEpisode|null findOneBy(array $criteria, array $orderBy = null)
 */
final class VideoShowEpisodeRepository extends AbstractAnzuRepository
{
    public function findOneLastByShow(VideoShow $videoShow): ?VideoShowEpisode
    {
        return $this->findOneBy(
            [
                'videoShow' => $videoShow->getId(),
            ],
            [
                'position' => 'DESC',
            ]
        );
    }

    protected function getEntityClass(): string
    {
        return VideoShowEpisode::class;
    }
}
