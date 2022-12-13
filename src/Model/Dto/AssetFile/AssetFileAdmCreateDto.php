<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetFile;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\Validator\Constraints as Assert;

abstract class AssetFileAdmCreateDto implements AssetFileAdmCreateDtoInterface
{
    #[Serialize]
    #[Assert\Length(max: 64, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    protected string $checksum = '';

    #[Serialize]
    #[Assert\NotBlank]
    protected string $mimeType = '';

    #[Serialize]
    #[Assert\Positive(message: ValidationException::ERROR_FIELD_LENGTH_MIN)]
    protected int $size = 0;

    public function getChecksum(): string
    {
        return $this->checksum;
    }

    public function setChecksum(string $checksum): self
    {
        $this->checksum = $checksum;

        return $this;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;

        return $this;
    }
}
