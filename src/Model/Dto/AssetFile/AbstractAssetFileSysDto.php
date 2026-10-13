<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetFile;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\CustomDataInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemInterface;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Symfony\Component\Validator\Constraints as Assert;

abstract class AbstractAssetFileSysDto implements ExtSystemInterface, CustomDataInterface
{
    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    protected array $customData = [];

    #[Serialize(handler: EntityIdHandler::class)]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    protected AssetLicence $licence;

    public function getLicence(): AssetLicence
    {
        return $this->licence;
    }

    public function setLicence(AssetLicence $licence): self
    {
        $this->licence = $licence;

        return $this;
    }

    public function hasLicence(): bool
    {
        return isset($this->licence);
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

    public function getAssetType(): AssetType
    {
        return AssetType::Image;
    }
}
