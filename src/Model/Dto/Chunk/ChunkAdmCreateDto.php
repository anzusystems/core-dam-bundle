<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Chunk;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints\NotBlank;

#[AppAssert\Chunk]
final class ChunkAdmCreateDto
{
    #[Serialize]
    #[NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    private int $offset = 0;

    #[Serialize]
    #[NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    private int $size = 0;
    private ?UploadedFile $file = null;
    private AssetFile $assetFile;

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function setOffset(int $offset): self
    {
        $this->offset = $offset;

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

    public function getFile(): ?UploadedFile
    {
        return $this->file;
    }

    public function setFile(?UploadedFile $file): self
    {
        $this->file = $file;

        return $this;
    }

    public function getAssetFile(): AssetFile
    {
        return $this->assetFile;
    }

    public function setAssetFile(AssetFile $assetFile): self
    {
        $this->assetFile = $assetFile;

        return $this;
    }
}
