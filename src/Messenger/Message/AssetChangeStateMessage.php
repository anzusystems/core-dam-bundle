<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Message;

use AnzuSystems\CoreDamBundle\Entity\Asset;

final class AssetChangeStateMessage
{
    private string $assetId;

    public function __construct(Asset $asset)
    {
        $this->assetId = (string) $asset->getId();
    }

    public function getAssetId(): ?string
    {
        return $this->assetId;
    }
}
