<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileFailedType;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class AssetFileStatusAdmNotificationDecorator extends AsseFileAdmNotificationDecorator
{
    private AssetFile $assetFile;

    public static function getInstance(AssetFile $assetFile): self
    {
        return parent::getBaseInstance(
            assetId: (string) $assetFile->getAsset()->getId(),
            assetFileId: (string) $assetFile->getId()
        )->setAssetFile($assetFile);
    }

    public function setAssetFile(AssetFile $assetFile): self
    {
        $this->assetFile = $assetFile;

        return $this;
    }

    public function getAssetFile(): AssetFile
    {
        return $this->assetFile;
    }

    #[Serialize]
    public function getStatus(): AssetFileProcessStatus
    {
        return $this->assetFile->getAssetAttributes()->getStatus();
    }

    #[Serialize]
    public function getFailReason(): AssetFileFailedType
    {
        return $this->assetFile->getAssetAttributes()->getFailReason();
    }

    #[Serialize]
    public function getAssetType(): AssetType
    {
        return $this->assetFile->getAsset()->getAttributes()->getAssetType();
    }

    #[Serialize]
    public function getOriginAssetFile(): string
    {
        return $this->assetFile->getAssetAttributes()->getOriginAssetId();
    }
}
