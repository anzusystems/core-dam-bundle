<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Podcast;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\Podcast;

class PodcastManager extends AbstractManager
{
    public function create(Podcast $podcast, bool $flush = true): Podcast
    {
        $this->trackCreation($podcast);
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
        $podcast->getTexts()
            ->setTitle($newPodcast->getTexts()->getTitle())
            ->setDescription($newPodcast->getTexts()->getDescription())
        ;
        $podcast->getAttributes()
            ->setRssUrl($newPodcast->getAttributes()->getRssUrl())
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
}
