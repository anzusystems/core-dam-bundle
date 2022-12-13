<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\RegionOfInterest;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\RegionOfInterest;
use AnzuSystems\CoreDamBundle\Model\Dto\AbstractEntityDto;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Symfony\Component\Validator\Constraints as Assert;

class RegionOfInterestAdmListDto extends AbstractEntityDto
{
    #[Assert\Length(max: 128, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    #[Serialize]
    protected string $title;

    #[Serialize(handler: EntityIdHandler::class)]
    protected ImageFile $image;
    protected int $position;

    public function __construct()
    {
        $this->setTitle('');
        $this->setPosition(0);
    }

    public static function getInstance(RegionOfInterest $regionOfInterest): static
    {
        return parent::getBaseInstance($regionOfInterest)
            ->setPosition($regionOfInterest->getPosition())
            ->setImage($regionOfInterest->getImage())
            ->setTitle($regionOfInterest->getTitle());
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    #[Serialize]
    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getImage(): ImageFile
    {
        return $this->image;
    }

    public function setImage(ImageFile $image): self
    {
        $this->image = $image;

        return $this;
    }
}
