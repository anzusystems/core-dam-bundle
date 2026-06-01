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

/**
 * Single place that hard-removes TTS audio files: CDN purge of the (now stale) public route, then the file row
 * incl. its routes + StorageCopy public-bucket object, then the stashed master bytes. Each file is deleted in its
 * own transaction (one failure must not block the rest) and the storage stash is flushed once at the end.
 *
 * Used by the retention cron ({@see TtsAudioRetentionFacade}), the regenerate orphan-cleanup, and the initial
 * failure cleanup. Safe for unslotted files.
 */
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
     * Nulls are accepted and skipped, so callers can pass optional files (e.g. a maybe-missing preview) directly.
     *
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

        // Drop the stashed master payloads once for the whole batch — emptyAll() re-walks the entire stash,
        // so calling it per file would be quadratic in the batch size.
        if ($removed > 0) {
            $this->fileStash->emptyAll();
        }

        return $removed;
    }

    private function removeOne(AudioFile $audioFile): bool
    {
        $audioFileId = (string) $audioFile->getId();

        try {
            $this->entityManager->wrapInTransaction(function () use ($audioFile): void {
                // Invalidate the old public URL on the CDN while its routes still exist, then delete the file —
                // delete() removes the routes + StorageCopy public-bucket object and stashes the master bytes.
                $this->routeFacade->dispatchRoutePurgeForAssetFiles([$audioFile]);
                $this->assetFileManager->delete($audioFile, false);
                $this->entityManager->flush();
            });

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
