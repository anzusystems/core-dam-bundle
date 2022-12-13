<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Domain\AssetFile;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class AssetAdmNotificationDecorator
{
    #[Serialize(serializedName: 'asset')]
    private string $assetId;

    public static function getInstance(string $assetId): self
    {
        return (new self())
            ->setAssetId($assetId);
    }

    public function getAssetId(): string
    {
        return $this->assetId;
    }

    public function setAssetId(string $assetId): self
    {
        $this->assetId = $assetId;

        return $this;
    }
}
