<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileStash;
use AnzuSystems\CoreDamBundle\Domain\AssetFileRoute\AssetFileRouteFacade;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

/** Hard-removes TTS audio files: CDN purge → file row → stashed master bytes. Per-file transactions; stash flushed once. */
final readonly class TtsAudioFileRemover
{
    public function __construct(
        private AssetFileManager $assetFileManager,
        private AssetFileRouteFacade $routeFacade,
        private FileStash $fileStash,
        private EntityManagerInterface $entityManager,
        private DamLogger $logger,
    ) {
    }

    /**
     * @return int the number of files actually removed
     */
    public function remove(?AudioFile ...$files): int
    {
        $removed = 0;
        foreach (array_filter($files) as $audioFile) {
            if ($this->removeOne($audioFile)) {
                $removed++;
            }
        }

        // emptyAll() re-walks the whole stash, so calling it per-file would be quadratic.
        if ($removed > 0) {
            $this->fileStash->emptyAll();
        }

        return $removed;
    }

    private function removeOne(AudioFile $audioFile): bool
    {
        $audioFileId = (string) $audioFile->getId();
        $assetId = (string) $audioFile->getAsset()->getId();
        $expireAt = $audioFile->getExpireAt()?->format('c');

        try {
            $this->entityManager->wrapInTransaction(function () use ($audioFile): void {
                $this->routeFacade->dispatchRoutePurgeForAssetFiles([$audioFile]);
                $this->assetFileManager->delete($audioFile, false);
                $this->entityManager->flush();
            });

            $this->logger->info(DamLogger::NAMESPACE_TTS, 'audioFileRemover.removed', [
                'audioFileId' => $audioFileId,
                'assetId' => $assetId,
                'expireAt' => $expireAt,
            ]);

            return true;
        } catch (Throwable $e) {
            $this->logger->warning(DamLogger::NAMESPACE_TTS, 'audioFileRemover.removeFailed', [
                'audioFileId' => $audioFileId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
