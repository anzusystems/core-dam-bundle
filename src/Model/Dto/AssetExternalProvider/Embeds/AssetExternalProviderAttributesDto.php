<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider\Embeds;

use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class AssetExternalProviderAttributesDto
{
    private AssetType $assetType;

    public static function getInstance(AssetType $assetType): self
    {
        return (new self())
            ->setAssetType($assetType)
        ;
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
}
