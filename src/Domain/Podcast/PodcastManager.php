<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Podcast;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Domain\ImagePreview\ImagePreviewManager;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Repository\PodcastRepository;

class PodcastManager extends AbstractManager
{
    private const int ATTRIBUTES_POSITION_INCREMENT = 100;

    public function __construct(
        private readonly ImagePreviewManager $imagePreviewManager,
        private readonly PodcastRepository $podcastRepository,
    ) {
    }

    public function create(Podcast $podcast, bool $flush = true): Podcast
    {
        $this->trackCreation($podcast);
        if ($podcast->getImagePreview()) {
            $this->imagePreviewManager->create($podcast->getImagePreview(), false);
        }
        $this->updateAttributesPositions($podcast);
        $this->entityManager->persist($podcast);
        $this->flush($flush);

        return $podcast;
    }

    public function updateExisting(Podcast $podcast, bool $flush = true): Podcast
    {
        $this->trackModification($podcast);
        $this->flush($flush);

        return $podcast;
    }

    public function update(Podcast $podcast, Podcast $newPodcast, bool $flush = true): Podcast
    {
        $this->trackModification($podcast);

        $podcast->setImagePreview(
            $this->imagePreviewManager->getNewImagePreview($podcast->getImagePreview(), $newPodcast->getImagePreview())
        );
        $podcast->setAltImage(
            $this->imagePreviewManager->getNewImagePreview($podcast->getAltImage(), $newPodcast->getAltImage())
        );

        $podcast->getTexts()
            ->setTitle($newPodcast->getTexts()->getTitle())
            ->setDescription($newPodcast->getTexts()->getDescription())
        ;
        $podcast->getAttributes()
            ->setRssUrl($newPodcast->getAttributes()->getRssUrl())
            ->setExtUrl($newPodcast->getAttributes()->getExtUrl())
            ->setFileSlot($newPodcast->getAttributes()->getFileSlot())
            ->setMode($newPodcast->getAttributes()->getMode())
            ->setMobileOrderPosition($newPodcast->getAttributes()->getMobileOrderPosition())
            ->setWebOrderPosition($newPodcast->getAttributes()->getWebOrderPosition())
        ;
        $podcast->getFlags()
            ->setMobilePublicExportEnabled($newPodcast->getFlags()->isMobilePublicExportEnabled())
            ->setWebPublicExportEnabled($newPodcast->getFlags()->isWebPublicExportEnabled())
        ;
        $podcast->getDates()
            ->setImportFrom($newPodcast->getDates()->getImportFrom())
        ;
        $this->flush($flush);

        return $podcast;
    }

    public function delete(Podcast $podcast, bool $flush = true): bool
    {
        $this->entityManager->remove($podcast);
        $this->flush($flush);

        return true;
    }

    private function updateAttributesPositions(Podcast $podcast): void
    {
        if (App::ZERO === $podcast->getAttributes()->getMobileOrderPosition()) {
            $lastEpisode = $this->podcastRepository->findOneLastMobile();

            $podcast->getAttributes()->setMobileOrderPosition(
                $lastEpisode?->getAttributes()->getMobileOrderPosition() + self::ATTRIBUTES_POSITION_INCREMENT
            );
        }
        if (App::ZERO === $podcast->getAttributes()->getWebOrderPosition()) {
            $lastEpisode = $this->podcastRepository->findOneLastWeb();

            $podcast->getAttributes()->setWebOrderPosition(
                $lastEpisode?->getAttributes()->getWebOrderPosition() + self::ATTRIBUTES_POSITION_INCREMENT
            );
        }
    }
}
