<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch;

use AnzuSystems\Contracts\Entity\Interfaces\BaseIdentifiableInterface;
use AnzuSystems\CoreDamBundle\Command\Traits\OutputUtilTrait;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\CustomForm\CustomFormProvider;
use AnzuSystems\CoreDamBundle\Elasticsearch\Exception\AnzuElasticSearchException;
use AnzuSystems\CoreDamBundle\Elasticsearch\Exception\InvalidRecordException;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexDefinition\IndexDefinitionFactory;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\DBALIndexableInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemIndexableInterface;
use AnzuSystems\CoreDamBundle\Exception\InvalidArgumentException;
use AnzuSystems\CoreDamBundle\Repository\AbstractAnzuRepository;
use AnzuSystems\CoreDamBundle\Repository\ExtSystemRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ElasticsearchException;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
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
        private readonly DBALRepositoryProvider $repositoryProvider,
        private readonly ExtSystemRepository $extSystemRepository,
        private readonly CustomFormProvider $customFormProvider,
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

        $config->setEntityName($this->indexSettings->getEntityClassName($config->getIndexName()));

        foreach ($this->getExtSystemsToIndexRebuild($config) as $extSystem) {
            $config->setCurrentExtSystemId((int) $extSystem->getId());
            $config->setCurrentExtSystemSlug($extSystem->getSlug());
            $config->setLastProcessedId(null);

            if ($config->isDrop()) {
                $this->dropAndCreateIndex($config);
            }
            $this->buildIndex($config);
        }
    }

    /**
     * @return array<int, ExtSystem>
     */
    private function getExtSystemsToIndexRebuild(RebuildIndexConfig $config): array
    {
        if ($config->hasExtSystemSlug()) {
            $extSystem = $this->extSystemRepository->findOneBySlug($config->getExtSystemSlug());
            if (null === $extSystem) {
                throw new InvalidArgumentException(sprintf('Ext system with slug (%s) not found', $config->getExtSystemSlug()));
            }

            return [$extSystem];
        }

        return $this->extSystemRepository->findAll();
    }

    /**
     * @throws ElasticsearchException
     */
    private function dropAndCreateIndex(RebuildIndexConfig $config): void
    {
        $indexNameFullName = $this->indexSettings->getFullIndexNameByConfig($config);
        $this->writeln(sprintf('Recreating index <info>%s</info>', $indexNameFullName));

        try {
            $this->client->indices()->delete([
                'index' => $indexNameFullName,
            ]);
        } catch (ClientResponseException $exception) {
            // Not found index is OK
            if (Response::HTTP_NOT_FOUND !== $exception->getResponse()->getStatusCode()) {
                throw $exception;
            }
        }
        $this->client->indices()->create([
            'index' => $indexNameFullName,
            'body' => $this->getIndexSettings($indexNameFullName),
        ]);
    }

    /**
     * @throws ElasticsearchException
     * @throws NonUniqueResultException
     */
    private function buildIndex(RebuildIndexConfig $config): void
    {
        $fullIndexName = $this->indexSettings->getFullIndexNameByConfig($config);
        $this->writeln(sprintf('Indexing <info>%s</info>...', $fullIndexName));

        /** @var AbstractAnzuRepository<BaseIdentifiableInterface> $repository */
        $repository = $this->entityManager->getRepository($this->indexSettings->getEntityClassName($config->getIndexName()));
        $count = $repository->getAllCountForIndexRebuild($config);
        $progressBar = $this->outputUtil->createProgressBar($count);
        $this->configureProgressBar($progressBar);

        $payload = [
            'body' => [],
        ];
        $i = 0;
        foreach ($this->iterate($config) as $item) {
            $i++;

            try {
                $payload['body'][] = [
                    'index' => [
                        '_index' => $fullIndexName,
                        '_id' => $item['id'],
                    ],
                ];
                $payload['body'][] = $item;
            } catch (InvalidRecordException) {
                $this->writeln(sprintf(
                    PHP_EOL . '<error>Skipping invalid record id %s</error>' . PHP_EOL,
                    (string) $item['id'],
                ));
            }

            if (0 === $i % $config->getBatchSize() && false === empty($payload['body'])) {
                $this->client->bulk($payload);
                $payload = [
                    'body' => [],
                ];
            }
        }

        if (false === empty($payload['body'])) {
            $this->client->bulk($payload);
        }

        $this->writeln(PHP_EOL);
    }

    /**
     * @return Generator<int, array>
     * @throws AnzuElasticSearchException
     * @throws InvalidRecordException
     */
    private function iterate(RebuildIndexConfig $config): Generator
    {
        return is_a($config->getEntityName(), DBALIndexableInterface::class, true)
            ? $this->iterateDBALRepository($config)
            : $this->iterateDoctrineRepository($config)
        ;
    }

    /**
     * @return Generator<int, array>
     *
     * @throws AnzuElasticSearchException
     * @throws InvalidRecordException
     */
    private function iterateDBALRepository(RebuildIndexConfig $config): Generator
    {
        /** @var class-string<DBALIndexableInterface> $className */
        $className = $config->getEntityName();
        $repository = $this->repositoryProvider->getRepository($className);
        $indexFactory = $this->indexFactoryProvider->getDBALIndexFactory($className);

        $progressBar = $this->getProgressBar($this->outputUtil->getOutput(), $repository->getAllCountForIndexRebuild($config));
        $progressBar->start();

        do {
            $items = $repository->getAllForIndexRebuild($config);
            foreach ($items as $item) {
                yield $indexFactory->buildFromArray($item);

                $progressBar->advance();
                $config->setLastProcessedId($item['id']);
            }
        } while ($config->getBatchSize() === count($items));

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

    /**
     * @return Generator<int, array>
     *
     * @throws AnzuElasticSearchException
     * @throws InvalidRecordException
     */
    private function iterateDoctrineRepository(RebuildIndexConfig $config): Generator
    {
        /** @var class-string<ExtSystemIndexableInterface> $className */
        $className = $config->getEntityName();
        /** @var AbstractAnzuRepository $repository */
        $repository = $this->entityManager->getRepository($className);
        $indexFactory = $this->indexFactoryProvider->getIndexFactory($className);

        $progressBar = $this->getProgressBar($this->outputUtil->getOutput(), $repository->getAllCountForIndexRebuild($config));
        $progressBar->start();

        do {
            /** @var Collection<int, ExtSystemIndexableInterface> $items */
            $items = $repository->getAllForIndexRebuild($config);
            foreach ($items as $item) {
                yield $indexFactory->buildFromEntity($item);

                $progressBar->advance();
                $config->setLastProcessedId($item->getId());
            }

            $this->entityManager->clear();
        } while ($config->getBatchSize() === $items->count());

        $progressBar->finish();
    }

    private function getProgressBar(OutputInterface $output, int $totalCount): ProgressBar
    {
        $progressBar = new ProgressBar($output, $totalCount);
        $progressBar->setFormat('debug');

        return $progressBar;
    }
}
