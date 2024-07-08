<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetFile;

use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\AssetCustomFormProvidableInterface;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;

final readonly class AssetCustomFormProvidableDto implements AssetCustomFormProvidableInterface
{
    public function __construct(
        private AssetType $assetType,
        private ExtSystem $extSystem,
    ) {
    }

    public function getAssetType(): AssetType
    {
        return $this->assetType;
    }

    public function getExtSystem(): ExtSystem
    {
        return $this->extSystem;
    }
}
