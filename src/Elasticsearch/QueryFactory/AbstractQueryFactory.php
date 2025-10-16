<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\QueryFactory;

use AnzuSystems\CoreDamBundle\Elasticsearch\IndexSettings;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\SearchDtoInterface;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use stdClass;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractQueryFactory implements QueryFactoryInterface
{
    private const string DEFAULT_TIMEOUT = '3s';
    private IndexSettings $indexSettings;

    #[Required]
    public function setIndexSettings(IndexSettings $indexSettings): void
    {
        $this->indexSettings = $indexSettings;
    }

    public function buildQuery(SearchDtoInterface $searchDto, ExtSystem $extSystem): array
    {
        $query = [
            'query' => [
                'bool' => [
                    'must' => $this->getMust($searchDto, $extSystem),
                    'filter' => $this->getFilter($searchDto, $extSystem),
                    'must_not' => $this->getMustNot($searchDto),
                ],
            ],
            'from' => $searchDto->getOffset(),
            'size' => $searchDto->getLimit(),
            'sort' => $searchDto->getOrder() ?: ['_score' => 'desc'],
        ];

        $scriptScoreFunctions = $this->getScriptScoreFunction($searchDto);
        if (is_array($scriptScoreFunctions)) {
            $originQuery = $query['query'];
            $query['query'] = [];
            $query['query']['function_score'] = [
                'query' => $originQuery,
                'functions' => $scriptScoreFunctions,
            ];
        }

        return [
            'index' => $this->indexSettings->getFullIndexNameBySlug($searchDto->getIndexName(), $extSystem->getSlug()),
            'body' => $query,
            'timeout' => self::DEFAULT_TIMEOUT,
        ];
    }

    public function getScriptScoreFunction(SearchDtoInterface $searchDto): ?array
    {
        return null;
    }

    protected function getMust(SearchDtoInterface $searchDto, ExtSystem $extSystem): array
    {
        return ['match_all' => new stdClass()];
    }

    protected function getMustNot(SearchDtoInterface $searchDto): array
    {
        $filter = [];
        if ($searchDto->getNotId()) {
            $filter[] = [
                'term' => [
                    '_id' => $searchDto->getNotId(),
                ],
            ];
        }

        return $filter;
    }

    protected function getFilter(SearchDtoInterface $searchDto, ExtSystem $extSystem): array
    {
        return [];
    }
}
