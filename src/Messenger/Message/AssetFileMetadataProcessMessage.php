<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Message;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Chunk;

final class AssetFileMetadataProcessMessage extends AbstractAssetFileMessage
{
    private string $chunkId;

    public function __construct(
        AssetFile $asset,
        Chunk $chunk
    ) {
        $this->chunkId = $chunk->getId();
        parent::__construct($asset);
    }

    public function getChunkId(): string
    {
        return $this->chunkId;
    }
}
