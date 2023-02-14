<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch;

use AnzuSystems\Contracts\Entity\Interfaces\BaseIdentifiableInterface;
use AnzuSystems\CoreDamBundle\Command\Traits\OutputUtilTrait;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexDefinition\IndexDefinitionFactory;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemIndexableInterface;
use AnzuSystems\CoreDamBundle\Repository\AbstractAnzuRepository;
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
        int|string $idFrom = 0,
        int|string $idUntil = 0,
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

    private function buildIndex(string $indexName, int $batch, int|string $idFrom, int|string $idUntil): void
    {
        $this->writeln('Indexing <info>' . $indexName . '</info>...');

        /** @var AbstractAnzuRepository<BaseIdentifiableInterface> $repo */
        $repo = $this->entityManager->getRepository($this->indexSettings->getEntityClassName($indexName));

        $count = $repo->getAllCount($idFrom, $idUntil);
        $progressBar = $this->outputUtil->createProgressBar($count);
        $this->configureProgressBar($progressBar);

        $maxId = $idUntil ?: $repo->getMaxId();
        do {
            $payload = ['body' => []];
            /** @var ExtSystemIndexableInterface $item */
            foreach ($repo->getAll($idFrom, $idUntil, $batch) as $item) {
                $payload['body'][] = [
                    'index' => [
                        '_index' => $this->indexSettings->getFullIndexNameByEntity($item),
                        '_id' => $item->getId(),
                    ],
                ];
                $payload['body'][] = $this->indexFactoryProvider->getIndexFactory($this->indexSettings->getEntityClassName($indexName))->buildFromEntity($item);

                $progressBar->advance();
                $idFrom = $item->getId();
            }
            if (false === empty($payload['body'])) {
                $this->client->bulk($payload);
            }
            $this->entityManager->clear();
        } while ($idFrom < $maxId);

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
