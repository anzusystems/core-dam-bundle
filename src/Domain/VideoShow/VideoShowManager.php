<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\VideoShow;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\VideoShow;

class VideoShowManager extends AbstractManager
{
    public function create(VideoShow $videoShow, bool $flush = true): VideoShow
    {
        $this->trackCreation($videoShow);
        $this->entityManager->persist($videoShow);
        $this->flush($flush);

        return $videoShow;
    }

    public function updateExisting(VideoShow $videoShow, bool $flush = true): VideoShow
    {
        $this->trackModification($videoShow);
        $this->flush($flush);

        return $videoShow;
    }

    public function update(VideoShow $videoShow, VideoShow $newVideoShow, bool $flush = true): VideoShow
    {
        $this->trackModification($videoShow);
        $videoShow->getTexts()->setTitle($newVideoShow->getTexts()->getTitle());
        $this->flush($flush);

        return $videoShow;
    }

    public function delete(VideoShow $videoShow, bool $flush = true): bool
    {
        $this->entityManager->remove($videoShow);
        $this->flush($flush);

        return true;
    }
}
