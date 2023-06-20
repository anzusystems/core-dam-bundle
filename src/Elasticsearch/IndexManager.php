<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch;

use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemIndexableInterface;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ElasticsearchException;
use Elastic\Elasticsearch\Response\Elasticsearch as ElasticsearchResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final readonly class IndexManager
{
    public function __construct(
        private Client $client,
        private IndexFactoryProvider $indexFactoryProvider,
        private IndexSettings $indexSettings,
    ) {
    }

    public function index(ExtSystemIndexableInterface $entity): bool
    {
        try {
            /** @var ElasticsearchResponse $response */
            $response = $this->client->index([
                'index' => $this->indexSettings->getFullIndexNameByEntity($entity),
                'id' => $entity->getId(),
                'body' => $this->indexFactoryProvider->getIndexFactory($entity::class)->buildFromEntity($entity),
            ]);
        } catch (ElasticsearchException $exception) {
            throw new RuntimeException('elastic_index_failed', 0, $exception);
        }

        if (in_array($response['result'], ['created', 'updated'], true)) {
            return true;
        }

        throw new RuntimeException('elastic_index_failed');
    }

    public function delete(ExtSystemIndexableInterface $entity, int|string $deletedId): bool
    {
        try {
            /** @var ElasticsearchResponse $response */
            $response = $this->client->delete([
                'index' => $this->indexSettings->getFullIndexNameByEntity($entity),
                'id' => $deletedId,
            ]);
        } catch (ElasticsearchException $exception) {
            // Not found record is OK
            if ($exception instanceof ClientResponseException
                && Response::HTTP_NOT_FOUND === $exception->getResponse()->getStatusCode()
            ) {
                return false;
            }

            throw new RuntimeException('elastic_delete_failed', 0, $exception);
        }

        if ('deleted' === $response['result']) {
            return true;
        }

        throw new RuntimeException('elastic_delete_failed');
    }
}
