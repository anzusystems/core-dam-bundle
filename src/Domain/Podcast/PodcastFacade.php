<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Podcast;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Validator\EntityValidator;

final class PodcastFacade
{
    public function __construct(
        private readonly EntityValidator $validator,
        private readonly PodcastManager $podcastManager,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function create(Podcast $podcast): Podcast
    {
        $this->validator->validate($podcast);
        $this->podcastManager->create($podcast);

        return $podcast;
    }

    /**
     * @throws ValidationException
     */
    public function update(Podcast $podcast, Podcast $newPodcast): Podcast
    {
        $this->validator->validate($newPodcast, $podcast);
        $this->podcastManager->update($podcast, $newPodcast);

        return $podcast;
    }

    public function delete(Podcast $podcast): bool
    {
        $this->podcastManager->delete($podcast);

        return true;
    }
}
