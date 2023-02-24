<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CommonBundle\Traits\LoggerAwareRequest;
use AnzuSystems\CoreDamBundle\Domain\Podcast\RssImportManager;
use AnzuSystems\CoreDamBundle\Repository\PodcastRepository;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'anzu-dam:podcast:read-rss',
    description: 'Reads Podcast RSS'
)]
final class ReadPodcastRssCommand extends Command
{
    use LoggerAwareRequest;
    private const ARG_PODCAST_ID = 'podcastId';

    public function __construct(
        private readonly RssImportManager $manager,
        private readonly PodcastRepository $repository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                self::ARG_PODCAST_ID,
                InputArgument::REQUIRED,
                'Podcast id to sync.'
            )
        ;
    }

    /**
     * @throws SerializerException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $podcast = $this->repository->find((string) $input->getArgument(self::ARG_PODCAST_ID));
        if (null === $podcast) {
            $output->writeln('Podcast not found');

            return Command::SUCCESS;
        }
        $this->manager->syncPodcast($podcast);

        return Command::SUCCESS;
    }
}
