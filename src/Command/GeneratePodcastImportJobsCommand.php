<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CommonBundle\Traits\LoggerAwareRequest;
use AnzuSystems\CoreDamBundle\Domain\Job\JobPodcastSynchronizerFactory;
use AnzuSystems\CoreDamBundle\Domain\Podcast\RssImportManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'anzu-dam:podcast:generate-import',
    description: 'Reads Podcast RSS'
)]
final class GeneratePodcastImportJobsCommand extends Command
{
    use LoggerAwareRequest;

    public function __construct(
        private readonly RssImportManager $rssImportManager,
        private readonly JobPodcastSynchronizerFactory $jobPodcastSynchronizerFactory,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->jobPodcastSynchronizerFactory->createPodcastSynchronizerJob(
            podcastId: '',
            fullSync: true
        );

        return Command::SUCCESS;
    }
}
