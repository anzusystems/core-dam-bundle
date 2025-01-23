<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\VideoShowEpisode;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\VideoShowEpisode;
use AnzuSystems\CoreDamBundle\Repository\VideoShowEpisodeRepository;

final class VideoShowEpisodeManager extends AbstractManager
{
    private const int ATTRIBUTES_POSITION_INCREMENT = 10;

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
        $videoShowEpisode->getTexts()
            ->setTitle($newVideoShowEpisode->getTexts()->getTitle())
        ;
        $videoShowEpisode->getAttributes()
            ->setMobileOrderPosition($newVideoShowEpisode->getAttributes()->getMobileOrderPosition())
            ->setWebOrderPosition($newVideoShowEpisode->getAttributes()->getWebOrderPosition())
        ;
        $videoShowEpisode->getFlags()
            ->setMobilePublicExportEnabled($newVideoShowEpisode->getFlags()->isMobilePublicExportEnabled())
            ->setWebPublicExportEnabled($newVideoShowEpisode->getFlags()->isWebPublicExportEnabled())
        ;
        $videoShowEpisode->getDates()
            ->setPublicationDate($newVideoShowEpisode->getDates()->getPublicationDate())
        ;
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
        if (App::ZERO === $videoShowEpisode->getPosition()) {
            $lastEpisode = $this->repository->findOneLastByShow($videoShowEpisode->getVideoShow());
            $videoShowEpisode->setPosition($lastEpisode?->getPosition() + 1);
        }

        if (App::ZERO === $videoShowEpisode->getAttributes()->getMobileOrderPosition()) {
            $lastEpisode = $this->repository->findOneLastMobile($videoShowEpisode->getVideoShow());

            $videoShowEpisode->getAttributes()->setMobileOrderPosition(
                $lastEpisode?->getAttributes()->getMobileOrderPosition() + self::ATTRIBUTES_POSITION_INCREMENT
            );
        }

        if (App::ZERO === $videoShowEpisode->getAttributes()->getWebOrderPosition()) {
            $lastEpisode = $this->repository->findOneLastWeb($videoShowEpisode->getVideoShow());

            $videoShowEpisode->getAttributes()->setWebOrderPosition(
                $lastEpisode?->getAttributes()->getWebOrderPosition() + self::ATTRIBUTES_POSITION_INCREMENT
            );
        }
    }
}
