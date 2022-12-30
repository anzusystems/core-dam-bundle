<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CoreDamBundle\Elasticsearch\IndexBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'anzu-dam:elastic:rebuild',
    description: 'Rebuild elastic index.'
)]
final class ElasticManagerCommand extends Command
{
    private const ARG_INDEX_NAME = 'indexName';
    private const ARG_ID_FROM = 'idFrom';
    private const ARG_ID_UNTIL = 'idUntil';
    private const OPT_NO_DROP = 'no-drop';
    private const OPT_BATCH = 'batch';

    public function __construct(
        private readonly IndexBuilder $indexBuilder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                self::ARG_INDEX_NAME,
                InputArgument::REQUIRED,
                'Index name to rebuild.'
            )->addArgument(
                self::ARG_ID_FROM,
                InputArgument::OPTIONAL,
                'First ID of entity to start processing from.',
                '0'
            )->addArgument(
                self::ARG_ID_UNTIL,
                InputArgument::OPTIONAL,
                'Last ID of entity to finish processing.',
                '0'
            )->addOption(
                self::OPT_NO_DROP,
                null,
                InputOption::VALUE_NONE,
                'If this option is set, the index will not be dropped.'
            )->addOption(
                self::OPT_BATCH,
                null,
                InputOption::VALUE_OPTIONAL,
                'Batch size for processing.',
                '500'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $idFrom = $input->getArgument(self::ARG_ID_FROM);
        $idUntil = $input->getArgument(self::ARG_ID_UNTIL);

        $this->indexBuilder->rebuildIndex(
            (string) $input->getArgument(self::ARG_INDEX_NAME),
            (bool) $input->getOption(self::OPT_NO_DROP),
            (int) $input->getOption(self::OPT_BATCH),
            is_numeric($idFrom) ? (int) $idFrom : (string) $idFrom,
            is_numeric($idUntil) ? (int) $idUntil : (string) $idUntil,
        );

        return Command::SUCCESS;
    }
}
