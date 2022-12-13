<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch;

use AnzuSystems\CommonBundle\Repository\AbstractAnzuRepository;
use AnzuSystems\CoreDamBundle\Command\Traits\OutputUtilTrait;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexDefinition\IndexDefinitionFactory;
use Doctrine\ORM\EntityManagerInterface;
use Elasticsearch\Client;
use Symfony\Component\Console\Helper\ProgressBar;

final class IndexBuilder
{
    use OutputUtilTrait;

    private array $indexDefinitions;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Client $client,
        private readonly IndexSettings $indexSettings,
        private readonly IndexFactoryProvider $indexFactoryProvider,
        private readonly ExtSystemConfigurationProvider $extSystemConfigurationProvider,
        private readonly array $indexMappings,
        IndexDefinitionFactory $indexDefinitionFactory,
    ) {
        $this->indexDefinitions = $indexDefinitionFactory
            ->buildIndexDefinitions($indexMappings);
    }

    public function rebuildIndex(
        string $indexName,
        bool $noDrop = false,
        int $batch = 500,
        int $idFrom = 0,
        int $idUntil = 0,
    ): void {
        if (false === in_array($indexName, $this->getAvailableIndexes(), true)) {
            $this->writeln(sprintf('ERROR: Index with name "%s" does not exist, skipping.', $indexName));

            return;
        }

        if (false === $noDrop) {
            $this->dropAndCreateIndex($indexName);
        }
        $this->buildIndex($indexName, $batch, $idFrom, $idUntil);
    }

    private function dropAndCreateIndex(string $indexName): void
    {
        $this->writeln(PHP_EOL . 'Recreating index <info>' . $indexName . '</info>...');
        $this->client->indices()->delete(['index' => $this->indexSettings->getIndexPrefix($indexName) . '_*']);

        foreach ($this->extSystemConfigurationProvider->getExtSystemSlugs() as $slug) {
            $fullIndexName = $this->indexSettings->getFullIndexNameBySlug($indexName, $slug);

            $this->client->indices()->create([
                'index' => $fullIndexName,
                'body' => $this->getIndexSettings($fullIndexName),
            ]);
        }
    }

    private function buildIndex(string $indexName, int $batch, int $idFrom, int $idUntil): void
    {
        $this->writeln('Indexing <info>' . $indexName . '</info>...');

        /** @var AbstractAnzuRepository $repo */
        $repo = $this->entityManager->getRepository($this->indexSettings->getEntityClassName($indexName));

        $progressBar = $this->outputUtil->createProgressBar();
        $this->configureProgressBar($progressBar);

        $payload = ['body' => []];

        foreach ($repo->findAll() as $item) {
            $payload['body'][] = [
                'index' => [
                    '_index' => $this->indexSettings->getFullIndexNameByEntity($item),
                    '_id' => $item->getId(),
                ],
            ];
            $payload['body'][] = $this->indexFactoryProvider->getIndexFactory($this->indexSettings->getEntityClassName($indexName))->buildFromEntity($item);

            $progressBar->advance();
        }

        $this->entityManager->clear();

        if (false === empty($payload['body'])) {
            $this->client->bulk($payload);
        }
        $progressBar->finish();
    }

    private function configureProgressBar(ProgressBar $progressBar): void
    {
        $progressBar->setRedrawFrequency(100);
        $progressBar->maxSecondsBetweenRedraws(3);
        $progressBar->minSecondsBetweenRedraws(1);
        $progressBar->setFormat('debug');
    }

    private function getIndexSettings(string $fullIndexName): array
    {
        return $this->indexDefinitions[$fullIndexName];
    }

    private function getAvailableIndexes(): array
    {
        return array_keys($this->indexMappings);
    }
}
