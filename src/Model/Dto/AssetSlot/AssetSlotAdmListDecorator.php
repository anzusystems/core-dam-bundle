<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetSlot;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetSlot;
use AnzuSystems\CoreDamBundle\Model\Dto\AbstractEntityDto;
use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\AssetFileHandler;
use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\ImageLinksHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

class AssetSlotAdmListDecorator extends AbstractEntityDto
{
    protected string $resourceName = AssetSlot::class;
    protected AssetSlot $assetSlot;

    public static function getInstance(AssetSlot $assetSlot): static
    {
        /** @psalm-var AssetSlotAdmListDecorator $parent */
        $parent = self::getBaseInstance($assetSlot);

        return $parent
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

    #[Serialize(handler: AssetFileHandler::class, type: ImageLinksHandler::TAG_LIST)]
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
        return $this->assetSlot->getFlags()->isDefault();
    }

    #[Serialize]
    public function isMain(): bool
    {
        return $this->assetSlot->getFlags()->isMain();
    }
}
