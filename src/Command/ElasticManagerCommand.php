<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Command;

use AnzuSystems\CoreDamBundle\Elasticsearch\IndexBuilder;
use AnzuSystems\CoreDamBundle\Elasticsearch\RebuildIndexConfig;
use Doctrine\ORM\NonUniqueResultException;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ElasticsearchException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
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
    public function __construct(
        private readonly IndexBuilder $indexBuilder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                RebuildIndexConfig::ARG_INDEX_NAME,
                InputArgument::REQUIRED,
                'Index name to rebuild.'
            )->addOption(
                RebuildIndexConfig::OPT_EXT_SYSTEM,
                null,
                InputOption::VALUE_OPTIONAL,
                'Ext system slug for which should be an entity rebuilt.'
            )->addOption(
                RebuildIndexConfig::OPT_ID_FROM,
                null,
                InputOption::VALUE_OPTIONAL,
                'First ID of entity to start processing from.',
            )->addOption(
                RebuildIndexConfig::OPT_ID_UNTIL,
                null,
                InputOption::VALUE_OPTIONAL,
                'Last ID of entity to finish processing.',
            )->addOption(
                RebuildIndexConfig::OPT_NO_DROP,
                null,
                InputOption::VALUE_NONE,
                'If this option is set, the index will not be dropped.'
            )->addOption(
                RebuildIndexConfig::OPT_BATCH,
                null,
                InputOption::VALUE_OPTIONAL,
                'Batch size for processing.',
                500
            )
        ;
    }

    /**
     * @throws ElasticsearchException
     * @throws NonUniqueResultException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = RebuildIndexConfig::createFromInput($input);

        $this->indexBuilder->rebuildIndex($config);

        return Command::SUCCESS;
    }
}
