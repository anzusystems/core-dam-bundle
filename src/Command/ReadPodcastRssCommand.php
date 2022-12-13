<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CommonBundle\Traits\LoggerAwareRequest;
use AnzuSystems\CoreDamBundle\Domain\Podcast\RssImportManager;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'anzu-dam:podcast:read-rss',
    description: 'Reads Podcast RSS'
)]
final class ReadPodcastRssCommand extends Command
{
    use LoggerAwareRequest;

    public function __construct(
        private readonly RssImportManager $manager,
    ) {
        parent::__construct();
    }

    /**
     * @throws SerializerException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->manager->readAllPodcastRss();

        return Command::SUCCESS;
    }
}
