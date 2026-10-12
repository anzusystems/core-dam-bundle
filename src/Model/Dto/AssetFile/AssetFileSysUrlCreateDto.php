<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetFile;

use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class AssetFileSysUrlCreateDto extends AbstractAssetFileSysUrlCreateDto
{
    #[Serialize]
    private bool $generatePublicRoute = false;

    public function isGeneratePublicRoute(): bool
    {
        return $this->generatePublicRoute;
    }

    public function setGeneratePublicRoute(bool $generatePublicRoute): void
    {
        $this->generatePublicRoute = $generatePublicRoute;
    }

    public function getAssetType(): AssetType
    {
        return AssetType::Image;
    }
}
