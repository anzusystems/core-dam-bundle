<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\QueryFactory;

use AnzuSystems\CoreDamBundle\Elasticsearch\IndexSettings;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\SearchDtoInterface;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Helper\StringHelper;
use stdClass;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractQueryFactory implements QueryFactoryInterface
{
    public const string DEFAULT_ORDER = self::SCORE_ORDER;
    public const string DEFAULT_ORDER_DIRECTION = 'desc';

    public const string SCORE_ORDER = '_score';
    public const string IDENTIFIER_ORDER = 'id';
    public const string CUSTOM_ORDER_SCORE_BEST = 'score_best';
    public const string CUSTOM_ORDER_SCORE_DATE = 'score_date';

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
        ];

        if ($this->isFullTextSearch($searchDto) && $this->isOrderScoreDate($searchDto)) {
            $scriptScoreFunctions = $this->getScriptScoreFunction($searchDto);
            if (is_array($scriptScoreFunctions)) {
                $originQuery = $query['query'];
                $query['query'] = [];
                $query['query']['function_score'] = [
                    'query' => $originQuery,
                    'functions' => $scriptScoreFunctions,
                ];
            }
        }

        $query['sort'] = $this->buildOrder($searchDto);

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

    public function isFullTextSearch(SearchDtoInterface $searchDto): bool
    {
        return false;
    }

    protected function getMust(SearchDtoInterface $searchDto, ExtSystem $extSystem): array
    {
        return ['match_all' => new stdClass()];
    }

    protected function isOrderScoreDate(SearchDtoInterface $searchDto): bool
    {
        if (isset($searchDto->getOrder()[self::CUSTOM_ORDER_SCORE_DATE])) {
            return true;
        }

        return false;
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

    protected function expandFulltextOrderFields(string $field, string $direction): array
    {
        return match ($field) {
            self::CUSTOM_ORDER_SCORE_BEST => [self::SCORE_ORDER => $direction],
            self::CUSTOM_ORDER_SCORE_DATE => [self::IDENTIFIER_ORDER => $direction],
            default => [$field => $direction],
        };
    }

    protected function expandRegularOrderFields(string $field, string $direction): array
    {
        return match ($field) {
            self::CUSTOM_ORDER_SCORE_BEST,
            self::CUSTOM_ORDER_SCORE_DATE => [self::IDENTIFIER_ORDER => $direction],
            default => [$field => $direction],
        };
    }

    protected function getDirection(string $direction): string
    {
        return StringHelper::isEmpty($direction) ? self::DEFAULT_ORDER_DIRECTION : $direction;
    }

    protected function getDefaultOrder(SearchDtoInterface $searchDto): array
    {
        return $this->isFullTextSearch($searchDto)
            ? $this->getFulltextDefaultOrder()
            : $this->getRegularDefaultOrder()
        ;
    }

    protected function getRegularDefaultOrder(): array
    {
        return [self::DEFAULT_ORDER => self::DEFAULT_ORDER_DIRECTION];
    }

    protected function getFulltextDefaultOrder(): array
    {
        return [self::DEFAULT_ORDER => self::DEFAULT_ORDER_DIRECTION];
    }

    private function buildOrder(SearchDtoInterface $searchDto): array
    {
        $expandedOrder = [];

        foreach ($searchDto->getOrder() as $field => $direction) {
            $direction = $this->getDirection($direction);

            $expandedFields = $this->isFullTextSearch($searchDto)
                ? $this->expandFulltextOrderFields($field, $direction)
                : $this->expandRegularOrderFields($field, $direction);
            foreach ($expandedFields as $expandedField => $expandedDirection) {
                $expandedOrder[$expandedField] = $expandedDirection;
            }
        }

        return $expandedOrder ?: $this->getDefaultOrder($searchDto);
    }
}
