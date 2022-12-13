<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Asset\Embeds;

use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetAttributes;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class AssetAttributesAdmDto
{
    private AssetType $assetType;
    private AssetStatus $assetStatus;

    public static function getInstance(AssetAttributes $attributes): self
    {
        return (new self())
            ->setAssetType($attributes->getAssetType())
            ->setAssetStatus($attributes->getStatus());
    }

    #[Serialize]
    public function getAssetType(): AssetType
    {
        return $this->assetType;
    }

    public function setAssetType(AssetType $assetType): self
    {
        $this->assetType = $assetType;

        return $this;
    }

    #[Serialize]
    public function getAssetStatus(): AssetStatus
    {
        return $this->assetStatus;
    }

    public function setAssetStatus(AssetStatus $assetStatus): self
    {
        $this->assetStatus = $assetStatus;

        return $this;
    }
}
