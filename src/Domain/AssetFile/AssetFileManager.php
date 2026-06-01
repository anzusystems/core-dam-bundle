<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFileRoute\AssetFileRouteManager;
use AnzuSystems\CoreDamBundle\Domain\AssetSlot\AssetSlotManager;
use AnzuSystems\CoreDamBundle\Domain\Chunk\ChunkFileManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Traits\FileStashAwareTrait;
use League\Flysystem\FilesystemException;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @template T of AssetFile
 */
class AssetFileManager extends AbstractManager
{
    use FileStashAwareTrait;

    protected AssetSlotManager $assetSlotManager;
    protected ChunkFileManager $chunkFileManager;
    protected AssetFileRouteManager $assetFileRouteManager;

    #[Required]
    public function setAssetSlotManager(AssetSlotManager $assetSlotManager): void
    {
        $this->assetSlotManager = $assetSlotManager;
    }

    #[Required]
    public function setChunkFileManager(ChunkFileManager $chunkFileManager): void
    {
        $this->chunkFileManager = $chunkFileManager;
    }

    #[Required]
    public function setAssetFileRouteManager(AssetFileRouteManager $assetFileRouteManager): void
    {
        $this->assetFileRouteManager = $assetFileRouteManager;
    }

    /**
     * @param T $assetFile
     *
     * @return T
     */
    public function updateExisting(AssetFile $assetFile, bool $flush = true, bool $trackModification = true): AssetFile
    {
        if ($trackModification) {
            $this->trackModification($assetFile);
        }
        $this->flush($flush);

        return $assetFile;
    }

    /**
     * @param T $assetFile
     */
    public function canBeRemoved(AssetFile $assetFile): bool
    {
        return true;
    }

    /**
     * @param T $assetFile
     *
     * @return T
     */
    public function create(AssetFile $assetFile, bool $flush = true): AssetFile
    {
        $this->trackCreation($assetFile);
        $this->entityManager->persist($assetFile);
        $this->flush($flush);

        return $assetFile;
    }

    /**
     * @param T $assetFile
     *
     * @throws FilesystemException
     */
    public function delete(AssetFile $assetFile, bool $flush = true): bool
    {
        foreach ($assetFile->getSlots() as $slot) {
            $this->assetSlotManager->delete($slot, false);
        }

        $this->chunkFileManager->clearChunks($assetFile, false);
        $this->assetFileRouteManager->clearRoutes($assetFile, false);

        $this->deleteAssetFileRelations($assetFile);
        if (false === empty($assetFile->getAssetAttributes()->getFilePath())) {
            $this->fileStash->add($assetFile);
        }
        $this->entityManager->remove($assetFile);
        $this->flush($flush);

        return true;
    }

    /**
     * @param T $assetFile
     */
    protected function deleteAssetFileRelations(AssetFile $assetFile): void
    {
    }
}
