<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetFile;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\CustomDataInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemInterface;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Symfony\Component\Validator\Constraints as Assert;

#[AppAssert\AssetFileSysCreate]
final class AssetFileSysCreateDto implements ExtSystemInterface, CustomDataInterface
{
    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\Length(max: 192, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private string $path = '';

    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    private array $customData = [];

    #[Serialize]
    private bool $generatePublicRoute = false;

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

    public function getCustomData(): array
    {
        return $this->customData;
    }

    public function setCustomData(array $customData): static
    {
        $this->customData = $customData;

        return $this;
    }

    public function isGeneratePublicRoute(): bool
    {
        return $this->generatePublicRoute;
    }

    public function setGeneratePublicRoute(bool $generatePublicRoute): void
    {
        $this->generatePublicRoute = $generatePublicRoute;
    }

    public function getAssetType(): AssetType
    {
        return AssetType::Image;
    }
}
