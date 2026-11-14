<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorMarkSourceFacade;
use AnzuSystems\CoreDamBundle\Exception\InvalidArgumentException;
use AnzuSystems\CoreDamBundle\Repository\ExtSystemRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'anzu-dam:author:mark-source',
    description: 'Mark the root authors named in a file (one name per line) as sources and move their image links to sources'
)]
final readonly class AuthorMarkSourceCommand
{
    public function __construct(
        private AuthorMarkSourceFacade $authorMarkSourceFacade,
        private ExtSystemRepository $extSystemRepository,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Slug of the ext system whose authors are marked')]
        string $extSystem = App::EMPTY_STRING,
        #[Option(description: 'Path of the file with one author name per line')]
        string $file = App::EMPTY_STRING,
        #[Option(description: 'Write nothing, only list the authors, their links and jobs')]
        bool $dryRun = false,
    ): int {
        $extSystemEntity = $this->extSystemRepository->findOneBySlug($extSystem);
        if (null === $extSystemEntity || App::EMPTY_STRING === $file) {
            $io->error('Both --ext-system=<slug> and --file=<path> are required');

            return Command::INVALID;
        }

        try {
            $authors = $this->authorMarkSourceFacade->readSources($extSystemEntity, $file);
        } catch (InvalidArgumentException $exception) {
            $io->error(['The file is not valid, nothing was changed.', $exception->getMessage()]);

            return Command::FAILURE;
        }

        $rows = $this->authorMarkSourceFacade->markSources($authors, $dryRun);
        $io->table(['Name', 'Id', 'Links', 'Job'], $rows);
        $jobs = count(array_filter($rows, static fn (array $row): bool => AuthorMarkSourceFacade::JOB_CREATED === $row['job']));
        $io->success(
            $dryRun
                ? sprintf('Dry run, nothing written. %d sources would be marked', count($rows))
                : sprintf('Marked %d sources, %d jobs', count($rows), $jobs)
        );

        return Command::SUCCESS;
    }
}
