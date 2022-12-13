<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\RegionOfInterest;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\RegionOfInterest;
use AnzuSystems\CoreDamBundle\Model\Dto\RegionOfInterest\RegionOfInterestAdmDetailDto;
use AnzuSystems\CoreDamBundle\Repository\RegionOfInterestRepository;

final class RegionOfInterestManager extends AbstractManager
{
    public function __construct(
        private readonly RegionOfInterestRepository $regionOfInterestRepository,
    ) {
    }

    public function create(RegionOfInterest $regionOfInterest, bool $flush = true): RegionOfInterest
    {
        $this->trackCreation($regionOfInterest);
        $this->makePositionUnique($regionOfInterest);
        $this->entityManager->persist($regionOfInterest);
        $this->flush($flush);

        return $regionOfInterest;
    }

    public function update(RegionOfInterest $regionOfInterest, RegionOfInterestAdmDetailDto $dto, bool $flush = true): RegionOfInterest
    {
        $this->trackModification($regionOfInterest);
        $regionOfInterest
            ->setPercentageHeight($dto->getPercentageHeight())
            ->setTitle($dto->getTitle())
            ->setPercentageWidth($dto->getPercentageWidth())
            ->setPointX($dto->getPointX())
            ->setPointY($dto->getPointY())
        ;
        $this->flush($flush);

        return $regionOfInterest;
    }

    public function delete(RegionOfInterest $regionOfInterest, bool $flush = true): bool
    {
        $this->entityManager->remove($regionOfInterest);
        $this->flush($flush);

        return true;
    }

    public function deleteByImage(ImageFile $assetFile): void
    {
        foreach ($assetFile->getRegionsOfInterest() as $roi) {
            $this->delete($roi, false);
        }
    }

    private function makePositionUnique(RegionOfInterest $regionOfInterest): void
    {
        $lastRoi = $this->regionOfInterestRepository->findLastByImage($regionOfInterest->getImage());
        if ($lastRoi) {
            $regionOfInterest->setPosition($lastRoi->getPosition() + 1);
        }
    }
}
