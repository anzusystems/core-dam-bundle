<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch;

use AnzuSystems\Contracts\Entity\Interfaces\BaseIdentifiableInterface;
use AnzuSystems\Contracts\Entity\Interfaces\IndexableInterface;
use AnzuSystems\CoreDamBundle\Command\Traits\OutputUtilTrait;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexDefinition\IndexDefinitionFactory;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemIndexableInterface;
use AnzuSystems\CoreDamBundle\Repository\AbstractAnzuRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ElasticsearchException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\HttpFoundation\Response;

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
        $this->indexDefinitions = $indexDefinitionFactory->buildIndexDefinitions($indexMappings);
    }

    /**
     * @throws ElasticsearchException
     * @throws NonUniqueResultException
     */
    public function rebuildIndex(RebuildIndexConfig $config): void
    {
        if (false === in_array($config->getIndexName(), $this->getAvailableIndexes(), true)) {
            $this->writeln(sprintf(
                'ERROR: Index with name "%s" does not exist, skipping.',
                $config->getIndexName(),
            ));

            return;
        }

        if ($config->isDrop()) {
            $this->dropAndCreateIndex($config);
        }
        $this->buildIndex($config);
    }

    /**
     * @throws ElasticsearchException
     */
    private function dropAndCreateIndex(RebuildIndexConfig $config): void
    {
        foreach ($this->getFullIndexNamesToRebuild($config) as $indexNameFullName) {
            $this->writeln(sprintf('Recreating index <info>%s</info>', $indexNameFullName));

            try {
                /** @psalm-suppress InvalidArgument */
                $this->client->indices()->delete([
                    'index' => $indexNameFullName,
                ]);
            } catch (ClientResponseException $exception) {
                // Not found index is OK
                if (Response::HTTP_NOT_FOUND !== $exception->getResponse()->getStatusCode()) {
                    throw $exception;
                }
            }
            /** @psalm-suppress InvalidArgument */
            $this->client->indices()->create([
                'index' => $indexNameFullName,
                'body' => $this->getIndexSettings($indexNameFullName),
            ]);
        }
    }

    /**
     * @throws ElasticsearchException
     * @throws NonUniqueResultException
     */
    private function buildIndex(RebuildIndexConfig $config): void
    {
        $this->writeln(sprintf('Indexing <info>%s</info>...', $config->getIndexName()));

        /** @var AbstractAnzuRepository<BaseIdentifiableInterface> $repository */
        $repository = $this->entityManager->getRepository($this->indexSettings->getEntityClassName($config->getIndexName()));
        $count = $repository->getAllCountForIndexRebuild($config);
        $progressBar = $this->outputUtil->createProgressBar($count);
        $this->configureProgressBar($progressBar);

        if ($config->hasNotIdUntil()) {
            $maxId = $repository->getMaxIdForIndexRebuild($config);
            if (empty($maxId)) {
                $this->writeln(sprintf('Skipping <info>%s</info>, nothing to index...', $config->getIndexName()));

                return;
            }
            $config->setMaxId($maxId);
        }
        do {
            $payload = ['body' => []];
            /** @var ExtSystemIndexableInterface $item */
            foreach ($repository->getAllForIndexRebuild($config) as $item) {
                $payload['body'][] = [
                    'index' => [
                        '_index' => $this->indexSettings->getFullIndexNameByEntity($item),
                        '_id' => $item->getId(),
                    ],
                ];
                $payload['body'][] = $this->indexFactoryProvider->getIndexFactory(
                    $this->indexSettings->getEntityClassName($config->getIndexName())
                )->buildFromEntity($item);

                $progressBar->advance();
                $config->setLastProcessedId($item->getId());
            }
            if (false === empty($payload['body'])) {
                /** @psalm-suppress InvalidArgument */
                $this->client->bulk($payload);
            }
            $this->entityManager->clear();
        } while ($config->getLastProcessedId() < $config->getResolvedMaxId());

        $progressBar->finish();
        $this->writeln(PHP_EOL);
    }

    /**
     * @return list<string>
     */
    private function getFullIndexNamesToRebuild(RebuildIndexConfig $config): array
    {
        if ($config->hasExtSystemSlug()) {
            return [$this->indexSettings->getFullIndexNameBySlug($config->getIndexName(), $config->getExtSystemSlug())];
        }

        return array_map(
            fn (string $extSystemSlug) => $this->indexSettings->getFullIndexNameBySlug($config->getIndexName(), $extSystemSlug),
            $this->extSystemConfigurationProvider->getExtSystemSlugs()
        );
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
