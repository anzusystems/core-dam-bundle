<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorNameNormalizeFacade;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'anzu-dam:author:normalize-names',
    description: 'Fill author.name_normalized (only the empty ones, or all with --all)'
)]
final readonly class AuthorNormalizeNamesCommand
{
    public function __construct(
        private AuthorNameNormalizeFacade $authorNameNormalizeFacade,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Recompute every author, not only the ones with an empty normalized name')]
        bool $all = false,
    ): int {
        $progressBar = $io->createProgressBar($this->authorNameNormalizeFacade->countAuthors($all));
        $progressBar->setFormat('debug');
        $progressBar->setBarCharacter('=');
        $progressBar->setEmptyBarCharacter('-');
        $progressBar->setProgressCharacter('>');

        $updated = App::ZERO;
        foreach ($this->authorNameNormalizeFacade->normalizeNames($all) as $written) {
            if ($written) {
                ++$updated;
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $io->newLine(2);
        $io->success(sprintf('Processed %d authors, updated %d', $progressBar->getProgress(), $updated));

        return Command::SUCCESS;
    }
}
