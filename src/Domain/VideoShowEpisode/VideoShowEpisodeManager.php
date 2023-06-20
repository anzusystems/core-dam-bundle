<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\VideoShowEpisode;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\VideoShowEpisode;
use AnzuSystems\CoreDamBundle\Repository\VideoShowEpisodeRepository;

final class VideoShowEpisodeManager extends AbstractManager
{
    public function __construct(
        private readonly VideoShowEpisodeRepository $repository,
    ) {
    }

    public function create(VideoShowEpisode $VideoShowEpisode, bool $flush = true): VideoShowEpisode
    {
        $this->setPosition($VideoShowEpisode);
        $this->trackCreation($VideoShowEpisode);
        $this->entityManager->persist($VideoShowEpisode);
        $this->flush($flush);

        return $VideoShowEpisode;
    }

    public function update(VideoShowEpisode $videoShowEpisode, VideoShowEpisode $newVideoShowEpisode, bool $flush = true): VideoShowEpisode
    {
        $this->trackModification($videoShowEpisode);
        $videoShowEpisode->getTexts()->setTitle($newVideoShowEpisode->getTexts()->getTitle());
        $videoShowEpisode->setAsset($newVideoShowEpisode->getAsset());
        $this->flush($flush);

        return $videoShowEpisode;
    }

    public function delete(VideoShowEpisode $podcast, bool $flush = true): bool
    {
        $this->entityManager->remove($podcast);
        $this->flush($flush);

        return true;
    }

    private function setPosition(VideoShowEpisode $videoShowEpisode): void
    {
        $lastEpisode = $this->repository->findOneLastByShow($videoShowEpisode->getVideoShow());
        if ($lastEpisode) {
            $videoShowEpisode->setPosition($lastEpisode->getPosition() + 1);
        }
    }
}
