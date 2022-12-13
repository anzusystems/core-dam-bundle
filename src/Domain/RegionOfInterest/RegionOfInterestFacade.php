<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\RegionOfInterest;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageManager;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\RegionOfInterest;
use AnzuSystems\CoreDamBundle\Model\Dto\RegionOfInterest\RegionOfInterestAdmDetailDto;
use AnzuSystems\CoreDamBundle\Validator\EntityValidator;

class RegionOfInterestFacade
{
    public function __construct(
        private readonly EntityValidator $entityValidator,
        private readonly RegionOfInterestManager $regionOfInterestManager,
        private readonly RegionOfInterestFactory $regionOfInterestFactory,
        private readonly ImageManager $imageManager,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function create(ImageFile $imageFile, RegionOfInterestAdmDetailDto $createDto): RegionOfInterest
    {
        $this->ensureSameImageEntity($imageFile, $createDto);
        $this->entityValidator->validateDto($createDto);
        $roi = $this->regionOfInterestFactory->createRoi($createDto);
        $this->imageManager->addRegionOfInterest($imageFile, $roi, false);

        return $this->regionOfInterestManager->create($roi);
    }

    /**
     * @throws ValidationException
     */
    public function update(
        RegionOfInterest $regionOfInterest,
        RegionOfInterestAdmDetailDto $roiDto,
    ): RegionOfInterest {
        $this->entityValidator->validateDto($roiDto);
        $this->regionOfInterestManager->update($regionOfInterest, $roiDto);

        return $regionOfInterest;
    }

    public function delete(RegionOfInterest $regionOfInterest): bool
    {
        $regionOfInterest->getImage()->getRegionsOfInterest()->removeElement($regionOfInterest);

        return $this->regionOfInterestManager->delete($regionOfInterest);
    }

    /**
     * @throws ValidationException
     */
    private function ensureSameImageEntity(ImageFile $imageFile, RegionOfInterestAdmDetailDto $createDto): void
    {
        if ($imageFile->getId() === $createDto->getImage()->getId()) {
            return;
        }

        throw (new ValidationException())
            ->addFormattedError('image', ValidationException::ERROR_FIELD_INVALID);
    }
}
