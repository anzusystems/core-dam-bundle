<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFileRoute\AssetFileRouteManager;
use AnzuSystems\CoreDamBundle\Domain\AssetSlot\AssetSlotManager;
use AnzuSystems\CoreDamBundle\Domain\Chunk\ChunkFileManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
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
     * Atomically swap the storage payload (file path, mime/size/checksum and audio
     * attributes) between two AudioFile rows without touching their Asset references
     * or routes. After the swap, $a holds what $b held and vice versa.
     *
     * Used by AssetSwap to flip a stable Asset's audio to the freshly-
     * synthesised content while the staging Asset receives the previous content
     * (so it can be safely deleted as garbage). Asset and slot bindings stay
     * intact, so all routes / URLs remain stable.
     *
     * Does NOT flush — caller is responsible (must run inside its own transaction).
     */
    public function swapContent(AudioFile $a, AudioFile $b, bool $flush = false): void
    {
        $aAssetAttrs = clone $a->getAssetAttributes();
        $aAudioAttrs = clone $a->getAttributes();

        $a->setAssetAttributes(clone $b->getAssetAttributes());
        $a->setAttributes(clone $b->getAttributes());

        $b->setAssetAttributes($aAssetAttrs);
        $b->setAttributes($aAudioAttrs);

        $this->flush($flush);
    }

    /**
     * @param T $assetFile
     */
    protected function deleteAssetFileRelations(AssetFile $assetFile): void
    {
    }
}
