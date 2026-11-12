<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Author\Merge\AuthorMergePlanFacade;
use AnzuSystems\CoreDamBundle\Repository\ExtSystemRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'anzu-dam:author:merge-plan',
    description: 'Write the CSV of duplicate authors (same normalized name and identifier) proposed for anzu-dam:author:merge'
)]
final readonly class AuthorMergePlanCommand
{
    public function __construct(
        private AuthorMergePlanFacade $authorMergePlanFacade,
        private ExtSystemRepository $extSystemRepository,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Slug of the ext system whose authors are merged')]
        string $extSystem = App::EMPTY_STRING,
        #[Option(description: 'Path of the CSV to write')]
        string $file = App::EMPTY_STRING,
    ): int {
        $extSystemEntity = $this->extSystemRepository->findOneBySlug($extSystem);
        if (null === $extSystemEntity || App::EMPTY_STRING === $file) {
            $io->error('Both --ext-system=<slug> and --file=<path> are required');

            return Command::INVALID;
        }

        $result = $this->authorMergePlanFacade->writePlan($extSystemEntity, $file);
        $io->success(sprintf(
            'Wrote %d authors in %d groups (%d left to people), %d variants proposed for merge, to %s',
            $result->authors,
            $result->groups,
            $result->manualGroups,
            $result->variants,
            $file,
        ));

        return Command::SUCCESS;
    }
}
