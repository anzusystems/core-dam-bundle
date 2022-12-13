<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Domain\AssetHasFile\AssetHasFileManager;
use AnzuSystems\CoreDamBundle\Domain\Chunk\ChunkManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Traits\FileStashAwareTrait;
use Symfony\Contracts\Service\Attribute\Required;

class AssetFileManager extends AbstractManager
{
    use FileStashAwareTrait;

    protected AssetHasFileManager $assetHasFileManager;
    protected ChunkManager $chunkManager;

    #[Required]
    public function setAssetHasFileManager(AssetHasFileManager $assetHasFileManager): void
    {
        $this->assetHasFileManager = $assetHasFileManager;
    }

    #[Required]
    public function setChunkManager(ChunkManager $chunkManager): void
    {
        $this->chunkManager = $chunkManager;
    }

    public function updateExisting(AssetFile $assetFile, bool $flush = true): AssetFile
    {
        $this->trackModification($assetFile);
        $this->flush($flush);

        return $assetFile;
    }

    public function create(AssetFile $assetFile, bool $flush = true): AssetFile
    {
        $this->trackCreation($assetFile);
        $this->entityManager->persist($assetFile);
        $this->flush($flush);

        return $assetFile;
    }

    public function delete(AssetFile $assetFile, bool $flush = true): bool
    {
        $this->assetHasFileManager->delete($assetFile->getAsset(), false);
        $this->chunkManager->deleteByAsset($assetFile);
        $this->deleteAssetFileRelations($assetFile);
        if (false === empty($assetFile->getAssetAttributes()->getFilePath())) {
            $this->fileStash->add($assetFile);
        }
        $this->entityManager->remove($assetFile);
        $this->flush($flush);

        return true;
    }

    protected function deleteAssetFileRelations(AssetFile $assetFile): void
    {
    }
}
