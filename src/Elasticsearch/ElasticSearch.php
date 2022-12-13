<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch;

use AnzuSystems\CommonBundle\ApiFilter\ApiInfiniteResponseList;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\SearchDtoInterface;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Repository\AbstractAnzuRepository;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Doctrine\ORM\EntityManagerInterface;
use Elasticsearch\Client;

final class ElasticSearch
{
    public function __construct(
        private readonly Client $client,
        private readonly EntityManagerInterface $entityManager,
        private readonly IndexSettings $idxSettings,
        private readonly DamLogger $damLogger,
        private readonly QueryFactoryProvider $queryFactoryProvider,
    ) {
    }

    /**
     * @throws SerializerException
     */
    public function searchInfiniteList(SearchDtoInterface $searchDto, ExtSystem $extSystem): ApiInfiniteResponseList
    {
        return $this->hydrateInfiniteResponseList(
            $searchDto,
            $this->client->search(
                $this->queryFactoryProvider->getQueryFactory($searchDto)->buildQuery($searchDto, $extSystem)
            ),
            $this->idxSettings->getEntityClassName($searchDto->getIndexName())
        );
    }

    /**
     * @psalm-param class-string $entityClassName
     *
     * @throws SerializerException
     */
    private function hydrateInfiniteResponseList(SearchDtoInterface $searchDto, array $elasticData, string $entityClassName): ApiInfiniteResponseList
    {
        $totalCount = $elasticData['hits']['total']['value'];
        if (0 === $totalCount) {
            return new ApiInfiniteResponseList();
        }

        return (new ApiInfiniteResponseList())
            ->setData($this->hydrateEntities($elasticData, $entityClassName))
            ->setHasNextPage(
                ($searchDto->getLimit() + $searchDto->getOffset()) < $totalCount
            );
    }

    /**
     * @psalm-param class-string $entityClassName
     *
     * @throws SerializerException
     */
    private function hydrateEntities(array $elasticData, string $entityClassName): array
    {
        $orderedEntityIds = array_map(static fn (array $item): string => $item['_id'], $elasticData['hits']['hits']);
        /** @var AbstractAnzuRepository $repo */
        $repo = $this->entityManager->getRepository($entityClassName);
        $entities = $repo->getAllByIdIndexed(...$orderedEntityIds);
        $orderedEntities = [];
        foreach ($orderedEntityIds as $id) {
            if (false === $entities->containsKey($id)) {
                $this->damLogger->error(
                    DamLogger::NAMESPACE_ELASTICSEARCH,
                    "Entity ({$entityClassName}) with id ({$id}) not exists in database"
                );

                continue;
            }
            $orderedEntities[] = $entities->get($id);
        }

        return $orderedEntities;
    }
}
