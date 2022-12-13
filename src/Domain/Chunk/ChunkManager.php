<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Chunk;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileStash;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Chunk;

final class ChunkManager extends AbstractManager
{
    public function __construct(
        private readonly FileStash $stash
    ) {
    }

    public function create(Chunk $chunk, bool $flush = true): Chunk
    {
        $this->trackCreation($chunk);
        $this->entityManager->persist($chunk);
        $this->flush($flush);

        return $chunk;
    }

    public function setAssetFile(Chunk $chunk, AssetFile $assetFile): Chunk
    {
        $chunk->setAssetFile($assetFile);
        $assetFile->getChunks()->add($chunk);

        return $chunk;
    }

    public function deleteByAsset(AssetFile $assetFile): void
    {
        foreach ($assetFile->getChunks() as $chunk) {
            $this->stash->add($chunk);
            $this->entityManager->remove($chunk);
        }
    }
}
