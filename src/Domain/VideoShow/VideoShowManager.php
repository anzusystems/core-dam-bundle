<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\VideoShow;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\VideoShow;
use AnzuSystems\CoreDamBundle\Repository\VideoShowRepository;

class VideoShowManager extends AbstractManager
{
    private const int ATTRIBUTES_POSITION_INCREMENT = 100;

    public function __construct(
        private readonly VideoShowRepository $videoShowRepository,
    ) {
    }

    public function create(VideoShow $videoShow, bool $flush = true): VideoShow
    {
        $this->trackCreation($videoShow);
        $this->updateAttributesPositions($videoShow);
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
        $videoShow->getTexts()
            ->setTitle($newVideoShow->getTexts()->getTitle())
        ;
        $videoShow->getAttributes()
            ->setMobileOrderPosition($newVideoShow->getAttributes()->getMobileOrderPosition())
            ->setWebOrderPosition($newVideoShow->getAttributes()->getWebOrderPosition())
        ;
        $videoShow->getFlags()
            ->setMobilePublicExportEnabled($newVideoShow->getFlags()->isMobilePublicExportEnabled())
            ->setWebPublicExportEnabled($newVideoShow->getFlags()->isWebPublicExportEnabled())
        ;
        $this->flush($flush);

        return $videoShow;
    }

    public function delete(VideoShow $videoShow, bool $flush = true): bool
    {
        $this->entityManager->remove($videoShow);
        $this->flush($flush);

        return true;
    }

    private function updateAttributesPositions(VideoShow $videoShow): void
    {
        if (App::ZERO === $videoShow->getAttributes()->getMobileOrderPosition()) {
            $last = $this->videoShowRepository->findOneLastMobile();

            $videoShow->getAttributes()->setMobileOrderPosition(
                $last?->getAttributes()->getMobileOrderPosition() + self::ATTRIBUTES_POSITION_INCREMENT
            );
        }
        if (App::ZERO === $videoShow->getAttributes()->getWebOrderPosition()) {
            $last = $this->videoShowRepository->findOneLastWeb();

            $videoShow->getAttributes()->setWebOrderPosition(
                $last?->getAttributes()->getWebOrderPosition() + self::ATTRIBUTES_POSITION_INCREMENT
            );
        }
    }
}
