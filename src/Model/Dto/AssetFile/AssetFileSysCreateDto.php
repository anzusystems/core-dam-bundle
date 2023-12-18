<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetFile;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemInterface;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Symfony\Component\Validator\Constraints as Assert;

final class AssetFileSysCreateDto implements ExtSystemInterface
{
    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\Length(max: 192, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private string $path;

    #[Serialize(handler: EntityIdHandler::class)]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    private AssetLicence $licence;

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function getLicence(): AssetLicence
    {
        return $this->licence;
    }

    public function setLicence(AssetLicence $licence): self
    {
        $this->licence = $licence;
        return $this;
    }

    public function getExtSystem(): ExtSystem
    {
        return $this->licence->getExtSystem();
    }
}
