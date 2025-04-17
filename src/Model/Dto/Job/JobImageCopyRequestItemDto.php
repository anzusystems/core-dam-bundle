<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Job;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Validator\Constraints as BaseAppAssert;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Symfony\Component\Validator\Constraints\NotBlank;

final class JobImageCopyRequestItemDto
{
    #[Serialize(handler: EntityIdHandler::class)]
    #[NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[BaseAppAssert\NotEmptyId]
    private ImageFile $imageFile;

    public function __construct()
    {
        $this->setImageFile(new ImageFile());
    }

    public function getImageFile(): ImageFile
    {
        return $this->imageFile;
    }

    public function setImageFile(ImageFile $imageFile): void
    {
        $this->imageFile = $imageFile;
    }
}
