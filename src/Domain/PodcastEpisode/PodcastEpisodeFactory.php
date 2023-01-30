<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\PodcastEpisode;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;

final class PodcastEpisodeFactory extends AbstractManager
{
    public function __construct(
        private readonly PodcastEpisodeManager $manager,
    ) {
    }

    public function createEpisodeWithAsset(Asset $asset, Podcast $podcast, bool $flush = true): PodcastEpisode
    {
        $podcastEpisode = (new PodcastEpisode())
            ->setAsset($asset)
            ->setPodcast($podcast)
        ;
        $asset->getEpisodes()->add($podcastEpisode);

        return $this->manager->create($podcastEpisode, $flush);
    }
}
