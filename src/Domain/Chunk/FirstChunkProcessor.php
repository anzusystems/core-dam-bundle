<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Chunk;

use AnzuSystems\CoreDamBundle\Entity\Chunk;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class FirstChunkProcessor
{
    public function process(Chunk $chunk, UploadedFile $file): void
    {
        $chunk->getAssetFile()
            ->getAssetAttributes()->setOriginFileName($file->getClientOriginalName());
    }
}
