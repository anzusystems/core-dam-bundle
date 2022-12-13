<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Asset;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\Validator\Constraints as Assert;

final class AssetAdmCreateDto
{
    #[Serialize]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: ValidationException::ERROR_FIELD_LENGTH_MIN,
        maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX
    )]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    private string $title;

    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    private AssetType $type;

    public function __construct()
    {
        $this->setType(AssetType::Default);
        $this->setTitle('');
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

    public function getType(): AssetType
    {
        return $this->type;
    }

    public function setType(AssetType $type): self
    {
        $this->type = $type;

        return $this;
    }
}
