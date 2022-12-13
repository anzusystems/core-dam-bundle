<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\PodcastEpisode;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\CoreDamBundle\Validator\EntityValidator;

final class PodcastEpisodeFacade
{
    public function __construct(
        private readonly EntityValidator $validator,
        private readonly PodcastEpisodeManager $podcastManager,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function create(PodcastEpisode $podcastEpisode): PodcastEpisode
    {
        $this->validator->validate($podcastEpisode);
        $this->podcastManager->create($podcastEpisode);

        return $podcastEpisode;
    }

    /**
     * @throws ValidationException
     */
    public function update(PodcastEpisode $podcastEpisode, PodcastEpisode $newPodcastEpisode): PodcastEpisode
    {
        $this->validator->validate($newPodcastEpisode, $podcastEpisode);
        $this->podcastManager->update($podcastEpisode, $newPodcastEpisode);

        return $podcastEpisode;
    }

    public function delete(PodcastEpisode $podcastEpisode): bool
    {
        $this->podcastManager->delete($podcastEpisode);

        return true;
    }
}
