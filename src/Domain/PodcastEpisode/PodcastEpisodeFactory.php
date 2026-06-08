<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\PodcastEpisode;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetTextsWriter;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\CoreDamBundle\Repository\PodcastEpisodeRepository;
use Doctrine\Common\Collections\Collection;

final class PodcastEpisodeFactory extends AbstractManager
{
    public function __construct(
        private readonly PodcastEpisodeManager $manager,
        private readonly PodcastEpisodeRepository $repository,
        private readonly AssetTextsWriter $assetTextsWriter,
        private readonly ExtSystemConfigurationProvider $extSystemConfigurationProvider,
    ) {
    }

    public function createEpisodeWithAsset(
        Asset $asset,
        Podcast $podcast,
        bool $flush = true,
        bool $inheritFromAsset = false,
    ): PodcastEpisode {
        // addEpisode() keeps the in-memory episode collection in sync for event consumers.
        $podcastEpisode = (new PodcastEpisode())->setPodcast($podcast);
        $asset->addEpisode($podcastEpisode);
        if ($inheritFromAsset) {
            $this->applyAssetDefaults($podcastEpisode, $asset);
        }

        return $this->manager->create($podcastEpisode, $flush);
    }

    /**
     * @param Collection<int, Podcast> $desiredPodcasts
     */
    public function setMembership(Asset $asset, Collection $desiredPodcasts, bool $flush = true, bool $inheritFromAsset = false): void
    {
        // Manual diff by podcastId: colUpdate assumes a homogeneous Collection.
        $currentByPodcastId = [];
        foreach ($asset->getEpisodes() as $episode) {
            $currentByPodcastId[(string) $episode->getPodcast()->getId()] = $episode;
        }

        $desiredByPodcastId = [];
        foreach ($desiredPodcasts as $podcast) {
            $desiredByPodcastId[(string) $podcast->getId()] = $podcast;
        }

        foreach (array_diff_key($desiredByPodcastId, $currentByPodcastId) as $podcast) {
            $this->createEpisodeWithAsset($asset, $podcast, false, $inheritFromAsset);
        }

        foreach (array_diff_key($currentByPodcastId, $desiredByPodcastId) as $episode) {
            $asset->removeEpisode($episode);
            $this->manager->delete($episode, false);
        }

        $this->flush($flush);
    }

    /**
     * Seed episode from asset: texts via entity map, next episode number, audio duration.
     */
    private function applyAssetDefaults(PodcastEpisode $episode, Asset $asset): void
    {
        $config = $this->extSystemConfigurationProvider->getAudioExtSystemConfiguration(
            $asset->getLicence()->getExtSystem()->getSlug()
        );
        $this->assetTextsWriter->writeValues($asset, $episode, $config->getPodcastEpisodeEntityMap());

        $mainFile = $asset->getMainFile();
        $lastEpisode = $this->repository->findOneLastByPodcast($episode->getPodcast());
        $episode->getAttributes()
            ->setEpisodeNumber(($lastEpisode?->getAttributes()->getEpisodeNumber() ?? App::ZERO) + 1)
            ->setDuration($mainFile instanceof AudioFile ? $mainFile->getAttributes()->getDuration() : App::ZERO)
        ;
    }
}
