<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event;

use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;

/**
 * The RSS intake kept an editorially uploaded file as the episode audio instead of downloading the enclosure.
 */
final readonly class PodcastEpisodeAudioAdoptedEvent
{
    public function __construct(
        private PodcastEpisode $podcastEpisode,
        private AudioFile $audioFile,
    ) {
    }

    public function getPodcastEpisode(): PodcastEpisode
    {
        return $this->podcastEpisode;
    }

    public function getAudioFile(): AudioFile
    {
        return $this->audioFile;
    }
}
