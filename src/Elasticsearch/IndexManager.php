<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch;

use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemIndexableInterface;
use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use RuntimeException;

final class IndexManager
{
    public function __construct(
        private readonly Client $client,
        private readonly IndexFactoryProvider $indexFactoryProvider,
        private readonly IndexSettings $indexSettings,
    ) {
    }

    public function index(ExtSystemIndexableInterface $entity): bool
    {
        $response = $this->client->index([
            'index' => $this->indexSettings->getFullIndexNameByEntity($entity),
            'id' => $entity->getId(),
            'body' => $this->indexFactoryProvider->getIndexFactory($entity::class)->buildFromEntity($entity),
        ]);

        if (in_array($response['result'], ['created', 'updated'], true)) {
            return true;
        }

        throw new RuntimeException('elastic_index_failed');
    }

    public function delete(ExtSystemIndexableInterface $entity, int|string $deletedId): bool
    {
        try {
            $response = $this->client->delete([
                'index' => $this->indexSettings->getFullIndexNameByEntity($entity),
                'id' => $deletedId,
            ]);

            if ('deleted' === $response['result']) {
                return true;
            }
        } catch (Missing404Exception) {
            return false;
        }

        throw new RuntimeException('elastic_delete_failed');
    }
}
