<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event;

final readonly class AssetFileRouteEvent
{
    public function __construct(
        private string $assetFileId,
        private string $fullUrl,
    ) {
    }

    public function getAssetFileId(): string
    {
        return $this->assetFileId;
    }

    public function getFullUrl(): string
    {
        return $this->fullUrl;
    }
}
