<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * @extends AbstractAnzuRepository<PodcastEpisode>
 *
 * @method PodcastEpisode|null find($id, $lockMode = null, $lockVersion = null)
 * @method PodcastEpisode|null findOneBy(array $criteria, array $orderBy = null)
 */
final class PodcastEpisodeRepository extends AbstractAnzuRepository
{
    public function findOneLastByPodcast(Podcast $podcast): ?PodcastEpisode
    {
        return $this->findOneBy(
            [
                'podcast' => $podcast->getId(),
            ],
            [
                'position' => 'DESC',
            ]
        );
    }

    public function findByTitleAndLicence(string $title, AssetLicence $licence): Collection
    {
        return new ArrayCollection(
            $this->createQueryBuilder('entity')
                ->innerJoin('entity.podcast', 'podcast')
                ->andWhere('entity.texts.title = :title')
                ->andWhere('IDENTITY(podcast.licence) = :licenceId')
                ->setParameter('title', $title)
                ->setParameter('licenceId', $licence->getId())
                ->getQuery()
                ->getResult()
        );
    }

    protected function getEntityClass(): string
    {
        return PodcastEpisode::class;
    }
}
