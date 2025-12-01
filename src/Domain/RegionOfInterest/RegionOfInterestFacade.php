<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\RegionOfInterest;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageManager;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\RegionOfInterest;
use AnzuSystems\CoreDamBundle\Event\ManipulatedImageEvent;
use AnzuSystems\CoreDamBundle\Model\Dto\RegionOfInterest\RegionOfInterestAdmDetailDto;
use AnzuSystems\CoreDamBundle\Traits\EventDispatcherAwareTrait;

class RegionOfInterestFacade
{
    use ValidatorAwareTrait;
    use EventDispatcherAwareTrait;

    public function __construct(
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
        $this->validator->validate($createDto);
        $roi = $this->regionOfInterestFactory->createRoi($createDto);
        $this->imageManager->addRegionOfInterest($imageFile, $roi, false);
        $imageFile->setManipulatedAt(App::getAppDate());

        return $this->regionOfInterestManager->create($roi);
    }

    /**
     * @throws ValidationException
     */
    public function update(
        RegionOfInterest $regionOfInterest,
        RegionOfInterestAdmDetailDto $roiDto,
    ): RegionOfInterest {
        $this->validator->validate($roiDto);
        $event = $this->createEvent($regionOfInterest);
        $regionOfInterest->getImage()->setManipulatedAt(App::getAppDate());
        $this->regionOfInterestManager->update($regionOfInterest, $roiDto);
        $this->dispatcher->dispatch($event);

        return $regionOfInterest;
    }

    public function delete(RegionOfInterest $regionOfInterest): bool
    {
        $event = $this->createEvent($regionOfInterest);
        $regionOfInterest->getImage()->setManipulatedAt(App::getAppDate());
        $regionOfInterest->getImage()->getRegionsOfInterest()->removeElement($regionOfInterest);

        if ($this->regionOfInterestManager->delete($regionOfInterest)) {
            $this->dispatcher->dispatch($event);

            return true;
        }

        return false;
    }

    private function createEvent(RegionOfInterest $regionOfInterest): ManipulatedImageEvent
    {
        return $this->dispatcher->dispatch(new ManipulatedImageEvent(
            imageId: (string) $regionOfInterest->getImage()->getId(),
            roiPositions: [$regionOfInterest->getPosition()],
            extSystem: $regionOfInterest->getImage()->getExtSystem()->getSlug()
        ));
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
