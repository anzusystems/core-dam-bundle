<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UuidIdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\Contracts\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\AssetLicenceInterface;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Repository\ImagePreviewRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints\NotBlank;

#[ORM\Entity(repositoryClass: ImagePreviewRepository::class)]
class ImagePreview implements
    UuidIdentifiableInterface,
    TimeTrackingInterface,
    UserTrackingInterface,
    AssetLicenceInterface
{
    use TimeTrackingTrait;
    use UuidIdentityTrait;
    use UserTrackingTrait;

    #[ORM\ManyToOne(targetEntity: ImageFile::class)]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Serialize(handler: EntityIdHandler::class)]
    protected ImageFile $imageFile;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Serialize]
    #[NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    private int $position = 0;

    public function __construct()
    {
        $this->setPosition(0);
        $this->setImageFile(new ImageFile());
    }

    public function getImageFile(): ImageFile
    {
        return $this->imageFile;
    }

    public function setImageFile(ImageFile $imageFile): self
    {
        $this->imageFile = $imageFile;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getLicence(): AssetLicence
    {
        return $this->getImageFile()->getLicence();
    }
}
