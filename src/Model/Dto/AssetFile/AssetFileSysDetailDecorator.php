<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetFile;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Model\Dto\AbstractEntityDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class AssetFileSysDetailDecorator extends AbstractEntityDto
{
    private AssetFile $assetFile;

    public static function getInstance(AssetFile $assetFile): static
    {
        return self::getBaseInstance($assetFile)
            ->setAssetFile($assetFile);
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

    #[Serialize]
    public function getOriginAssetFileId(): string
    {
        return $this->assetFile->getAssetAttributes()->getOriginAssetId();
    }

    #[Serialize]
    public function getAssetFileStatus(): AssetFileProcessStatus
    {
        return $this->assetFile->getAssetAttributes()->getStatus();
    }

    #[Serialize]
    public function getAssetId(): ?string
    {
        return $this->assetFile->getAsset()->getId();
    }

    #[Serialize]
    public function getAssetType(): AssetType
    {
        return $this->assetFile->getAssetType();
    }

    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    public function getCustomData(): array
    {
        return $this->assetFile->getAsset()->getMetadata()->getCustomData();
    }
}
