<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Domain\AssetSlot\AssetSlotManager;
use AnzuSystems\CoreDamBundle\Domain\Chunk\ChunkManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Traits\FileStashAwareTrait;
use Symfony\Contracts\Service\Attribute\Required;

class AssetFileManager extends AbstractManager
{
    use FileStashAwareTrait;

    protected AssetSlotManager $assetSlotManager;
    protected ChunkManager $chunkManager;

    #[Required]
    public function setAssetSlotManager(AssetSlotManager $assetSlotManager): void
    {
        $this->assetSlotManager = $assetSlotManager;
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
        foreach ($assetFile->getSlots() as $slot) {
            $this->assetSlotManager->delete($slot, false);
        }

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