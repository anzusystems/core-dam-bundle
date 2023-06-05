<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CoreDamBundle\Domain\Audio\AudioFacade;
use AnzuSystems\CoreDamBundle\Domain\Document\DocumentFacade;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFacade;
use AnzuSystems\CoreDamBundle\Domain\Video\VideoFacade;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'anzu-dam:asset-file:clear',
    description: 'Clear assetFiles in failed/duplicate state'
)]
final class ClearDuplicatesAndFailedFilesCommand extends Command
{
    public function __construct(
        private readonly ImageFacade $imageFacade,
        private readonly AudioFacade $audioFacade,
        private readonly DocumentFacade $documentFacade,
        private readonly VideoFacade $videoFacade,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(
            sprintf(
                'Deleting image failed/duplicate uploads. Deleted %d.',
                $this->imageFacade->deleteFailedAndDuplicates()
            ),
        );
        $output->writeln(
            sprintf(
                'Deleting audio failed/duplicate uploads. Deleted %d.',
                $this->audioFacade->deleteFailedAndDuplicates()
            ),
        );
        $output->writeln(
            sprintf(
                'Deleting document failed/duplicate uploads. Deleted %d.',
                $this->documentFacade->deleteFailedAndDuplicates()
            ),
        );
        $output->writeln(
            sprintf(
                'Deleting video failed/duplicate uploads. Deleted %d.',
                $this->videoFacade->deleteFailedAndDuplicates()
            ),
        );

        return Command::SUCCESS;
    }
}
