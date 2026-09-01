<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Image;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

final class ImageFirstUseItemDto
{
    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\Uuid(message: ValidationException::ERROR_FIELD_INVALID)]
    private string $damId = '';

    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    private ?DateTimeImmutable $firstUsedAt = null;

    public function getDamId(): string
    {
        return $this->damId;
    }

    public function setDamId(string $damId): self
    {
        $this->damId = $damId;

        return $this;
    }

    public function getFirstUsedAt(): ?DateTimeImmutable
    {
        return $this->firstUsedAt;
    }

    public function setFirstUsedAt(?DateTimeImmutable $firstUsedAt): self
    {
        $this->firstUsedAt = $firstUsedAt;

        return $this;
    }
}
