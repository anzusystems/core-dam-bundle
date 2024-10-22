<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Message;

use AnzuSystems\CoreDamBundle\Entity\Asset;

final class CopyAssetFileMessage
{
    private string $assetId;
    private string $copyAssetId;

    public function __construct(
        Asset $asset,
        Asset $copyAsset,
    )
    {
        $this->assetId = (string) $asset->getId();
        $this->copyAssetId = (string) $copyAsset->getId();
    }

    public function getAssetId(): string
    {
        return $this->assetId;
    }

    public function getCopyAssetId(): string
    {
        return $this->copyAssetId;
    }
}
