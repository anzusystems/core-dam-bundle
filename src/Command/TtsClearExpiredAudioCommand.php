<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAudioRetentionFacade;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Cron entrypoint: delete superseded TTS audio files whose retention grace has elapsed (kept alive so their
 * old public CDN URLs stay streamable for a window after a regeneration). Register alongside the other DAM
 * GC crons (e.g. `anzu-dam:asset:clear`).
 */
#[AsCommand(
    name: 'anzu-dam:tts:clear-expired-audio',
    description: 'Delete superseded TTS audio files whose retention grace period has elapsed'
)]
final class TtsClearExpiredAudioCommand extends Command
{
    private const int BATCH_LIMIT = 200;

    public function __construct(
        private readonly TtsAudioRetentionFacade $retentionFacade,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(
            sprintf(
                'Deleting expired TTS audio. Deleted count (%d).',
                $this->retentionFacade->deleteExpired(self::BATCH_LIMIT)
            ),
        );

        return Command::SUCCESS;
    }
}
