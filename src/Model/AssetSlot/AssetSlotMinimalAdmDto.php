<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\AssetSlot;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Symfony\Component\Validator\Constraints as Assert;

#[AppAssert\AssetSlotName]
final class AssetSlotMinimalAdmDto
{
    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    private string $slotName = '';

    #[Serialize(handler: EntityIdHandler::class)]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    private AssetFile $assetFile;

    public function getSlotName(): string
    {
        return $this->slotName;
    }

    public function setSlotName(string $slotName): self
    {
        $this->slotName = $slotName;

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
