<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Entity\VideoShow;
use AnzuSystems\CoreDamBundle\Entity\VideoShowEpisode;
use Doctrine\Common\Collections\Order;

/**
 * @extends AbstractAnzuRepository<VideoShowEpisode>
 *
 * @method VideoShowEpisode|null find($id, $lockMode = null, $lockVersion = null)
 * @method VideoShowEpisode|null findOneBy(array $criteria, array $orderBy = null)
 */
class VideoShowEpisodeRepository extends AbstractAnzuRepository
{
    public function findOneLastByShow(VideoShow $videoShow): ?VideoShowEpisode
    {
        return $this->findOneBy(
            [
                'videoShow' => $videoShow->getId(),
            ],
            [
                'position' => Order::Descending->value,
            ]
        );
    }

    public function findOneLastMobile(VideoShow $videoShow): ?VideoShowEpisode
    {
        return $this->findOneBy(
            [
                'videoShow' => $videoShow->getId(),
            ],
            [
                'attributes.mobileOrderPosition' => Order::Descending->value,
            ]
        );
    }

    public function findOneLastWeb(VideoShow $videoShow): ?VideoShowEpisode
    {
        return $this->findOneBy(
            [
                'videoShow' => $videoShow->getId(),
            ],
            [
                'attributes.webOrderPosition' => Order::Descending->value,
            ]
        );
    }

    protected function getEntityClass(): string
    {
        return VideoShowEpisode::class;
    }
}
