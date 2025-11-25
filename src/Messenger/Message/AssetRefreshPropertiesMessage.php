<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Message;

final readonly class AssetRefreshPropertiesMessage
{
    public function __construct(
        private string $assetId
    ) {
    }

    public function getAssetId(): string
    {
        return $this->assetId;
    }
}
