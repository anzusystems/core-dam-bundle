<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event\Dispatcher;

use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\CoreDamBundle\Event\PodcastEpisodeAudioAdoptedEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final readonly class PodcastEpisodeEventDispatcher
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function dispatchAudioAdopted(PodcastEpisode $podcastEpisode, AudioFile $audioFile): void
    {
        $this->dispatcher->dispatch(new PodcastEpisodeAudioAdoptedEvent($podcastEpisode, $audioFile));
    }
}
