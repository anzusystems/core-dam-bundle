<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CommonBundle\Traits\LoggerAwareRequest;
use AnzuSystems\CoreDamBundle\Domain\Job\JobPodcastSynchronizerFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'anzu-dam:podcast:generate-import',
    description: 'Reads Podcast RSS'
)]
final class GeneratePodcastImportJobsCommand extends Command
{
    use LoggerAwareRequest;

    private const string OPT_PODCAST_ID = 'podcast-id';

    public function __construct(
        private readonly JobPodcastSynchronizerFactory $jobPodcastSynchronizerFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                self::OPT_PODCAST_ID,
                null,
                InputOption::VALUE_OPTIONAL,
                'Podcast ID to synchronize (if not provided, all podcasts will be processed)',
                ''
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $podcastId = (string) $input->getOption(self::OPT_PODCAST_ID);

        $this->jobPodcastSynchronizerFactory->createPodcastSynchronizerJob(
            podcastId: $podcastId,
            fullSync: empty($podcastId)
        );

        return Command::SUCCESS;
    }
}
