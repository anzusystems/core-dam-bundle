<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileStash;
use AnzuSystems\CoreDamBundle\Domain\AssetFileRoute\AssetFileRouteFacade;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Repository\AudioFileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

/**
 * Reaps superseded TTS audio files whose retention grace ({@see \AnzuSystems\CoreDamBundle\Entity\AssetFile::getExpireAt()})
 * has elapsed. Driven by the {@see \AnzuSystems\CoreDamBundle\Command\TtsClearExpiredAudioCommand} cron.
 *
 * Each file is deleted whole: its routes (and the StorageCopy public-bucket object behind the old CDN URL),
 * its slots (none — it was demoted on regen), and its master storage bytes. A CDN purge is dispatched first so
 * the now-stale old URL is invalidated. Per-file, best-effort: one failure must not block the rest of the batch.
 */
final readonly class TtsAudioRetentionFacade
{
    public function __construct(
        private AudioFileRepository $audioFileRepository,
        private AssetFileManager $assetFileManager,
        private AssetFileRouteFacade $routeFacade,
        private FileStash $fileStash,
        private EntityManagerInterface $entityManager,
        private DamLogger $logger,
    ) {
    }

    public function deleteExpired(int $limit): int
    {
        $expired = $this->audioFileRepository->findExpired(App::getAppDate(), $limit);

        $deleted = 0;
        foreach ($expired as $audioFile) {
            if ($this->deleteOne($audioFile)) {
                $deleted++;
            }
        }

        // Drop the stashed master payloads once for the whole batch — emptyAll() re-walks the entire stash,
        // so calling it per file would be quadratic in the batch size.
        if ($deleted > 0) {
            $this->fileStash->emptyAll();
        }

        return $deleted;
    }

    private function deleteOne(AudioFile $audioFile): bool
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
            $this->logger->warning(DamLogger::NAMESPACE_TTS, 'audioRetention.deleteExpiredFailed', [
                'audioFileId' => $audioFileId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
