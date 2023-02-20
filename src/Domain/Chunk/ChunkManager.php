<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Chunk;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileStash;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Chunk;
use Symfony\Contracts\Service\Attribute\Required;

class ChunkManager extends AbstractManager
{
    protected FileStash $stash;

    #[Required]
    public function setStash(FileStash $stash): void
    {
        $this->stash = $stash;
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
}
