<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Repository\AudioFileRepository;

/**
 * Reaps superseded TTS audio files whose retention grace ({@see \AnzuSystems\CoreDamBundle\Entity\AssetFile::getExpireAt()})
 * has elapsed. Driven by the {@see \AnzuSystems\CoreDamBundle\Command\TtsClearExpiredAudioCommand} cron.
 *
 * Deletion (routes + StorageCopy public-bucket object + master bytes, per-file best-effort) is delegated to
 * {@see TtsAudioFileRemover}.
 */
final readonly class TtsAudioRetentionFacade
{
    public function __construct(
        private AudioFileRepository $audioFileRepository,
        private TtsAudioFileRemover $audioFileRemover,
    ) {
    }

    public function deleteExpired(int $limit): int
    {
        $expired = $this->audioFileRepository->findExpired(App::getAppDate(), $limit);

        return $this->audioFileRemover->remove(...$expired->getValues());
    }
}
