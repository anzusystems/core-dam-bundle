<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Author\Merge\AuthorMergeFacade;
use AnzuSystems\CoreDamBundle\Exception\InvalidArgumentException;
use AnzuSystems\CoreDamBundle\Repository\ExtSystemRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'anzu-dam:author:merge',
    description: 'Merge duplicate authors by the CSV of anzu-dam:author:merge-plan checked by people'
)]
final readonly class AuthorMergeCommand
{
    public function __construct(
        private AuthorMergeFacade $authorMergeFacade,
        private ExtSystemRepository $extSystemRepository,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Slug of the ext system whose authors are merged')]
        string $extSystem = App::EMPTY_STRING,
        #[Option(description: 'Path of the checked CSV')]
        string $file = App::EMPTY_STRING,
        #[Option(description: 'Merge everything in a transaction that is rolled back, without jobs and Elasticsearch')]
        bool $dryRun = false,
    ): int {
        $extSystemEntity = $this->extSystemRepository->findOneBySlug($extSystem);
        if (null === $extSystemEntity || App::EMPTY_STRING === $file) {
            $io->error('Both --ext-system=<slug> and --file=<path> are required');

            return Command::INVALID;
        }

        try {
            $groups = $this->authorMergeFacade->readGroups($extSystemEntity, $file);
        } catch (InvalidArgumentException $exception) {
            $io->error(['The CSV is not valid, nothing was changed.', $exception->getMessage()]);

            return Command::FAILURE;
        }

        $progressBar = $io->createProgressBar(count($groups));
        $merge = $this->authorMergeFacade->merge($groups, $dryRun);
        foreach ($merge as $ignored) {
            $progressBar->advance();
        }
        $progressBar->finish();
        $io->newLine(2);

        $result = $merge->getReturn();
        $skipped = $result->getSkippedGroups();
        if (false === empty($skipped)) {
            $io->table(['Skipped group', 'Reason'], array_map(null, array_keys($skipped), $skipped));
        }
        $io->success(sprintf(
            '%sRun %s: merged %d groups (%d variants, %d asset links, %d clean phrases), %d jobs, %d groups already merged, %d skipped',
            $dryRun ? 'Dry run, rolled back. ' : App::EMPTY_STRING,
            $result->getRunId(),
            $result->getMergedGroups(),
            $result->getMergedVariants(),
            $result->getAssetLinks(),
            $result->getCleanPhrases(),
            $result->getJobs(),
            $result->getAlreadyMergedGroups(),
            count($skipped),
        ));

        return Command::SUCCESS;
    }
}
