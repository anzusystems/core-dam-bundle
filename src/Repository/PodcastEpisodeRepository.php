<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;

/**
 * Not final on purpose — the host app extends it (App\Repository\PodcastEpisodeRepository).
 *
 * @extends AbstractAnzuRepository<PodcastEpisode>
 *
 * @method PodcastEpisode|null find($id, $lockMode = null, $lockVersion = null)
 * @method PodcastEpisode|null findOneBy(array $criteria, array $orderBy = null)
 */
class PodcastEpisodeRepository extends AbstractAnzuRepository
{
    /**
     * @return string[]
     */
    public function findPodcastIdsByAsset(Asset $asset): array
    {
        $result = $this->createQueryBuilder('entity')
            ->select('IDENTITY(entity.podcast) AS podcastId')
            ->andWhere('IDENTITY(entity.asset) = :assetId')
            ->setParameter('assetId', $asset->getId())
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): string => (string) $row['podcastId'], $result);
    }

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

    public function findOneTitleAndPodcast(string $title, Podcast $podcast): ?PodcastEpisode
    {
        return $this->createQueryBuilder('entity')
            ->andWhere('entity.texts.title = :title')
            ->andWhere('IDENTITY(entity.podcast) = :podcastId')
            ->setParameter('title', $title)
            ->setParameter('podcastId', $podcast->getId())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    protected function getEntityClass(): string
    {
        return PodcastEpisode::class;
    }
}
