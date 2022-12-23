<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\AssetSlot;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetSlot;
use AnzuSystems\CoreDamBundle\Model\Enum\ImageCropTag;
use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\AssetFileHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

class AssetSlotAdmListDto
{
    protected string $resourceName = AssetSlot::class;
    protected AssetSlot $assetSlot;

    public static function getInstance(AssetSlot $assetSlot): static
    {
        return (new self())
            ->setAssetSlot($assetSlot);
    }

    public function getAssetSlot(): AssetSlot
    {
        return $this->assetSlot;
    }

    public function setAssetSlot(AssetSlot $assetSlot): self
    {
        $this->assetSlot = $assetSlot;

        return $this;
    }

    #[Serialize(handler: AssetFileHandler::class, type: ImageCropTag::LIST)]
    public function getAssetFile(): AssetFile
    {
        return $this->assetSlot->getAssetFile();
    }

    #[Serialize]
    public function getSlotName(): string
    {
        return $this->assetSlot->getName();
    }

    #[Serialize]
    public function isDefault(): bool
    {
        return $this->assetSlot->isDefault();
    }

    #[Serialize]
    public function isMain(): bool
    {
        // todo implement main
        return $this->assetSlot->isDefault();
    }
}
