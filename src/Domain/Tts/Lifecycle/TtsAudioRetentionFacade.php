<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Repository\AudioFileRepository;

/**
 * Reaps superseded TTS audio files past their retention grace (cron-driven); per-file deletion via
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
