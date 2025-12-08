<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Domain\AssetFile;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

class AsseFileAdmNotificationDecorator
{
    #[Serialize(serializedName: 'asset')]
    protected string $assetId;

    #[Serialize(serializedName: 'id')]
    protected string $assetFileId;

    public static function getBaseInstance(string $assetId, string $assetFileId): static
    {
        return (new static())
            ->setAssetId($assetId)
            ->setAssetFileId($assetFileId);
    }

    public function getAssetId(): string
    {
        return $this->assetId;
    }

    public function setAssetId(string $assetId): static
    {
        $this->assetId = $assetId;

        return $this;
    }

    public function getAssetFileId(): string
    {
        return $this->assetFileId;
    }

    public function setAssetFileId(string $assetFileId): static
    {
        $this->assetFileId = $assetFileId;

        return $this;
    }
}
