<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\VideoShowEpisode;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Entity\VideoShowEpisode;

final class VideoShowEpisodeFacade
{
    use ValidatorAwareTrait;

    public function __construct(
        private readonly VideoShowEpisodeManager $videoShowEpisodeManager,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function create(VideoShowEpisode $videoShowEpisode): VideoShowEpisode
    {
        $this->validator->validate($videoShowEpisode);
        $this->videoShowEpisodeManager->create($videoShowEpisode);

        return $videoShowEpisode;
    }

    /**
     * @throws ValidationException
     */
    public function update(VideoShowEpisode $videoShowEpisode, VideoShowEpisode $newVideoShowEpisode): VideoShowEpisode
    {
        $this->validator->validate($newVideoShowEpisode, $videoShowEpisode);
        $this->videoShowEpisodeManager->update($videoShowEpisode, $newVideoShowEpisode);

        return $videoShowEpisode;
    }

    public function delete(VideoShowEpisode $videoShowEpisode): bool
    {
        $this->videoShowEpisodeManager->delete($videoShowEpisode);

        return true;
    }
}
