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
        // addEpisode() (not setAsset()) so the asset's in-memory episode collection stays in sync — the
        // asset-changed event consumers (TTS publish, indexing) read it right after this call.
        $podcastEpisode = (new PodcastEpisode())->setPodcast($podcast);
        $asset->addEpisode($podcastEpisode);
        if ($inheritFromAsset) {
            $this->applyAssetDefaults($podcastEpisode, $asset);
        }

        return $this->manager->create($podcastEpisode, $flush);
    }

    /**
     * Replace the full set of podcast memberships for an asset (PUT semantics): found the missing episodes,
     * delete the extra ones.
     *
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
     * Seed a freshly founded episode with data from its asset: title/description via the configured
     * podcast_episode_entity_map, next episode number and audio duration. Public-export (web/app) is NOT
     * set here — that is the host app's publishing concern.
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
