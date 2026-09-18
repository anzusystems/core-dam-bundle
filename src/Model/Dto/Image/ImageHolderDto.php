<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Image;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Stable identity of whoever claims a single use photo: an article version resolves it to its docId,
 * everything else uses its own id ({@see UsageClaim}).
 */
final class ImageHolderDto
{
    #[Serialize]
    #[NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    private string $name = App::EMPTY_STRING;

    #[Serialize]
    #[NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    private string $id = App::EMPTY_STRING;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }
}
