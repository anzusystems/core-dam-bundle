<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\QueryFactory;

use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\SearchDtoInterface;
use AnzuSystems\CoreDamBundle\Helper\StringHelper;

final class QueryOrderBuilder
{
    public const string SCORE_ORDER = '_score';
    public const string IDENTIFIER_ORDER = '_score';
    public const string DEFAULT_ORDER_DIRECTION = 'desc';

    public const string CUSTOM_ORDER_SCORE_BEST = 'score_best';
    public const string CUSTOM_ORDER_SCORE_DATE = 'score_date';

    public function buildFulltextSearchOrder(SearchDtoInterface $searchDto): array
    {
        $expandedOrder = [];

        foreach ($searchDto->getOrder() as $field => $direction) {
            $expandedFields = $this->getExpandedFields($field, $direction);
            foreach ($expandedFields as $expandedField => $expandedDirection) {
                $expandedOrder[$expandedField] = $expandedDirection;
            }
        }

        return $expandedOrder ?: [self::SCORE_ORDER => self::DEFAULT_ORDER_DIRECTION];
    }

    public function isOrderScoreDate(SearchDtoInterface $searchDto): bool
    {
        if (isset($searchDto->getOrder()[self::CUSTOM_ORDER_SCORE_DATE])) {
            return true;
        }

        return false;
    }

    private function getExpandedFields(string $field, string $direction): array
    {
        $direction = StringHelper::isEmpty($direction) ? self::DEFAULT_ORDER_DIRECTION : $direction;

        return match ($field) {
            self::CUSTOM_ORDER_SCORE_BEST, self::CUSTOM_ORDER_SCORE_DATE => [
                self::SCORE_ORDER => self::DEFAULT_ORDER_DIRECTION,
                'createdAt' => $direction,
            ],
            default => [$field => $direction],
        };
    }
}
