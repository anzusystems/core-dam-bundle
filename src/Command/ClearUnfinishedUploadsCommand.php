<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CoreDamBundle\Domain\Asset\AssetFacade;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'anzu-dam:asset:clear',
    description: 'Clear unfinished uploads'
)]
final class ClearUnfinishedUploadsCommand extends Command
{
    public function __construct(
        private readonly AssetFacade $assetFacade,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(
            sprintf(
                'Deleting unfinished uploads. Deleted count (%d).',
                $this->assetFacade->deleteUnfinishedUploads()
            ),
        );

        return Command::SUCCESS;
    }
}
