<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Message;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;

abstract class AbstractAssetFileMessage
{
    private string $assetId;

    public function __construct(AssetFile $asset)
    {
        $this->assetId = (string) $asset->getId();
    }

    public function getAssetId(): string
    {
        return $this->assetId;
    }
}
