<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorNameNormalizer;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'anzu-dam:author:normalize-names',
    description: 'Fill author.name_normalized (only the empty ones, or all with --all)'
)]
final class AuthorNormalizeNamesCommand extends Command
{
    private const string OPT_ALL = 'all';
    private const int BATCH_SIZE = 1_000;

    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        $this->addOption(
            name: self::OPT_ALL,
            mode: InputOption::VALUE_NONE,
            description: 'Recompute every author, not only the ones with an empty normalized name',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $onlyEmpty = false === (bool) $input->getOption(self::OPT_ALL);
        $lastId = App::EMPTY_STRING;
        $processed = App::ZERO;
        $updated = App::ZERO;

        while (true) {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT id, name, name_normalized FROM author WHERE id > :lastId'
                . ($onlyEmpty ? " AND name_normalized = ''" : '')
                . ' ORDER BY id ASC LIMIT ' . self::BATCH_SIZE,
                ['lastId' => $lastId],
            );
            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $lastId = (string) $row['id'];
                $normalized = AuthorNameNormalizer::normalize((string) $row['name']);
                ++$processed;
                if ($normalized === $row['name_normalized']) {
                    continue;
                }

                $this->connection->executeStatement(
                    'UPDATE author SET name_normalized = :normalized WHERE id = :id',
                    ['normalized' => $normalized, 'id' => $lastId],
                );
                ++$updated;
            }

            $output->writeln(sprintf('Processed %d authors, updated %d', $processed, $updated));
        }

        $output->writeln(sprintf('<info>Done: processed %d authors, updated %d</info>', $processed, $updated));

        return Command::SUCCESS;
    }
}
