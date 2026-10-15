<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetFile;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\Validator\Constraints as Assert;

#[AppAssert\AssetFileSysCreatePath]
final class AssetFileSysPathCreateDto extends AbstractAssetFileSysDto
{
    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\Length(max: 192, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private string $path = '';

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;

        return $this;
    }
}
