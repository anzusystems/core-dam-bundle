<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event;

use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;

final readonly class AssetFileRouteEvent
{
    public function __construct(
        private string $assetFileId,
        private AssetType $assetType,
        private string $fullUrl,
    ) {
    }

    public function getAssetFileId(): string
    {
        return $this->assetFileId;
    }

    public function getAssetType(): AssetType
    {
        return $this->assetType;
    }

    public function getFullUrl(): string
    {
        return $this->fullUrl;
    }
}
