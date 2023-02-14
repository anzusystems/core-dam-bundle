<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\PodcastEpisode;

use AnzuSystems\CoreDamBundle\Domain\Asset\AssetTextsWriter;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAudioTypeConfiguration;
use AnzuSystems\CoreDamBundle\Repository\PodcastEpisodeRepository;
use InvalidArgumentException;

final class PodcastEpisodeBodyFacade
{
    public function __construct(
        private readonly PodcastEpisodeManager $podcastManager,
        private readonly AssetTextsWriter $assetTextsWriter,
        private readonly ExtSystemConfigurationProvider $extSystemConfigurationProvider,
        private readonly PodcastEpisodeRepository $podcastEpisodeRepository,
    ) {
    }

    public function preparePayload(Asset $asset, Podcast $podcast): PodcastEpisode
    {
        $config = $this->extSystemConfigurationProvider->getExtSystemConfigurationByAsset($asset);
        if (false === ($config instanceof ExtSystemAudioTypeConfiguration)) {
            throw new InvalidArgumentException('Asset type must be a type of audio');
        }

        $episode = (new PodcastEpisode())
            ->setPodcast($podcast)
            ->setAsset($asset);

        $this->podcastManager->trackModification($episode);
        $this->podcastManager->trackCreation($episode);

        $this->assetTextsWriter->writeValues(
            from: $asset,
            to: $episode,
            config: $config->getPodcastEpisodeEntityMap()
        );

        $this->setNumbers($podcast, $episode);

        return $episode;
    }

    private function setNumbers(Podcast $podcast, PodcastEpisode $podcastEpisode): void
    {
        $lastEpisode = $this->podcastEpisodeRepository->findOneLastByPodcast($podcast);
        if (null === $lastEpisode) {
            return;
        }

        $podcastEpisode->getAttributes()
            ->setSeasonNumber($lastEpisode->getAttributes()->getSeasonNumber())
            ->setEpisodeNumber(
                $lastEpisode->getAttributes()->getEpisodeNumber()
                    ? $lastEpisode->getAttributes()->getEpisodeNumber() + 1
                    : null
            )
        ;
    }
}
