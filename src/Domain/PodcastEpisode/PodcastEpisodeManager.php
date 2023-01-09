<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\PodcastEpisode;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\CoreDamBundle\Repository\PodcastEpisodeRepository;

final class PodcastEpisodeManager extends AbstractManager
{
    public function __construct(
        private readonly PodcastEpisodeRepository $repository,
    ) {
    }

    public function create(PodcastEpisode $podcastEpisode, bool $flush = true): PodcastEpisode
    {
        $this->setPosition($podcastEpisode);
        $this->trackCreation($podcastEpisode);
        $this->entityManager->persist($podcastEpisode);
        $this->flush($flush);

        return $podcastEpisode;
    }

    public function update(PodcastEpisode $podcastEpisode, PodcastEpisode $newPodcastEpisode, bool $flush = true): PodcastEpisode
    {
        $this->trackModification($podcastEpisode);
        $podcastEpisode->getAttributes()
            ->setSeasonNumber($newPodcastEpisode->getAttributes()->getSeasonNumber())
            ->setEpisodeNumber($newPodcastEpisode->getAttributes()->getEpisodeNumber())
        ;
        $podcastEpisode->getTexts()
            ->setTitle($newPodcastEpisode->getTexts()->getTitle())
            ->setDescription($newPodcastEpisode->getTexts()->getDescription())
        ;
        $podcastEpisode->getDates()
            ->setPublicationDate($newPodcastEpisode->getDates()->getPublicationDate())
        ;
        $podcastEpisode
            ->setAsset($newPodcastEpisode->getAsset())
            ->setPreviewImage($newPodcastEpisode->getPreviewImage())
        ;
        $this->flush($flush);

        return $podcastEpisode;
    }

    public function delete(PodcastEpisode $podcast, bool $flush = true): bool
    {
        $this->entityManager->remove($podcast);
        $this->flush($flush);

        return true;
    }

    private function setPosition(PodcastEpisode $podcastEpisode): void
    {
        $lastEpisode = $this->repository->findOneLastByPodcast($podcastEpisode->getPodcast());
        if ($lastEpisode) {
            $podcastEpisode->setPosition($lastEpisode->getPosition() + 1);
        }
    }
}
