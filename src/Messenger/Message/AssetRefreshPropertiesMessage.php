<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Message;

final class AssetRefreshPropertiesMessage
{
    public function __construct(
        private readonly string $assetId
    ) {
    }

    public function getAssetId(): ?string
    {
        return $this->assetId;
    }
}
