<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemSynchronizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'anzu-dam:ext-system:sync',
    description: 'Synchronize ext system by base configuration.'
)]
final class SyncExtSystemsCommand extends Command
{
    public function __construct(
        private readonly ExtSystemSynchronizer $extSystemSynchronizer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->extSystemSynchronizer->synchronizeExtSystems();

        return Command::SUCCESS;
    }
}
