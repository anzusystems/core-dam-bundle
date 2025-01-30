<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\PodcastEpisode;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Domain\ImagePreview\ImagePreviewManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\CoreDamBundle\Repository\PodcastEpisodeRepository;
use Doctrine\Common\Collections\ArrayCollection;

class PodcastEpisodeManager extends AbstractManager
{
    private const int ATTRIBUTES_POSITION_MODIFIER = 10;

    public function __construct(
        private readonly PodcastEpisodeRepository $repository,
        private readonly ImagePreviewManager $imagePreviewManager
    ) {
    }

    public function create(PodcastEpisode $podcastEpisode, bool $flush = true): PodcastEpisode
    {
        $this->setPosition($podcastEpisode);
        $this->updateAttributesPositions($podcastEpisode);
        $podcastEpisode->setLicence($podcastEpisode->getPodcast()->getLicence());
        if ($podcastEpisode->getImagePreview()) {
            $this->imagePreviewManager->create($podcastEpisode->getImagePreview(), false);
        }
        $this->trackCreation($podcastEpisode);
        $this->entityManager->persist($podcastEpisode);
        $this->flush($flush);

        return $podcastEpisode;
    }

    public function updateExisting(PodcastEpisode $podcastEpisode, bool $flush = true): PodcastEpisode
    {
        $this->trackModification($podcastEpisode);
        $this->flush($flush);

        return $podcastEpisode;
    }

    public function moveEpisodes(Asset $fromAsset, Asset $toAsset, bool $flush = true): void
    {
        foreach ($fromAsset->getEpisodes() as $episode) {
            $toAsset->addEpisode($episode);
        }
        $fromAsset->setEpisodes(new ArrayCollection());

        $this->flush($flush);
    }

    public function update(PodcastEpisode $podcastEpisode, PodcastEpisode $newPodcastEpisode, bool $flush = true): PodcastEpisode
    {
        $this->trackModification($podcastEpisode);

        $podcastEpisode->setImagePreview(
            $this->imagePreviewManager->getNewImagePreview($podcastEpisode->getImagePreview(), $newPodcastEpisode->getImagePreview())
        );

        $podcastEpisode->getAttributes()
            ->setSeasonNumber($newPodcastEpisode->getAttributes()->getSeasonNumber())
            ->setEpisodeNumber($newPodcastEpisode->getAttributes()->getEpisodeNumber())
            ->setWebOrderPosition($newPodcastEpisode->getAttributes()->getWebOrderPosition())
            ->setMobileOrderPosition($newPodcastEpisode->getAttributes()->getMobileOrderPosition())
            ->setExtUrl($newPodcastEpisode->getAttributes()->getExtUrl())
            ->setDuration($newPodcastEpisode->getAttributes()->getDuration())
        ;
        $podcastEpisode->getTexts()
            ->setTitle($newPodcastEpisode->getTexts()->getTitle())
            ->setDescription($newPodcastEpisode->getTexts()->getDescription())
        ;
        $podcastEpisode->getDates()
            ->setPublicationDate($newPodcastEpisode->getDates()->getPublicationDate())
        ;
        $podcastEpisode
            ->setAsset($newPodcastEpisode->getAsset())
        ;
        $podcastEpisode->getFlags()
            ->setMobilePublicExportEnabled($newPodcastEpisode->getFlags()->isMobilePublicExportEnabled())
            ->setWebPublicExportEnabled($newPodcastEpisode->getFlags()->isWebPublicExportEnabled())
        ;
        $podcastEpisode->setLicence($podcastEpisode->getPodcast()->getLicence());
        $this->flush($flush);

        return $podcastEpisode;
    }

    public function delete(PodcastEpisode $podcast, bool $flush = true): bool
    {
        $this->entityManager->remove($podcast);
        $this->flush($flush);

        return true;
    }

    private function setPosition(PodcastEpisode $podcastEpisode): void
    {
        $lastEpisode = $this->repository->findOneLastByPodcast($podcastEpisode->getPodcast());
        if ($lastEpisode) {
            $podcastEpisode->setPosition($lastEpisode->getPosition() + 1);
        }
    }

    private function updateAttributesPositions(PodcastEpisode $podcastEpisode): void
    {
        if (App::ZERO === $podcastEpisode->getAttributes()->getMobileOrderPosition()) {
            $podcastEpisode->getAttributes()->setMobileOrderPosition(
                $podcastEpisode->getPosition() * self::ATTRIBUTES_POSITION_MODIFIER
            );
        }
        if (App::ZERO === $podcastEpisode->getAttributes()->getWebOrderPosition()) {
            $podcastEpisode->getAttributes()->setWebOrderPosition(
                $podcastEpisode->getPosition() * self::ATTRIBUTES_POSITION_MODIFIER
            );
        }
    }
}
