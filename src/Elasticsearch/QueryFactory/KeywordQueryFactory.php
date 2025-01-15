<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\QueryFactory;

use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\KeywordAdmSearchDto;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\SearchDtoInterface;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;

final class KeywordQueryFactory extends AbstractQueryFactory
{
    public function getSupportedSearchDtoClasses(): array
    {
        return [
            KeywordAdmSearchDto::class,
        ];
    }

    /**
     * @param KeywordAdmSearchDto $searchDto
     */
    protected function getMust(SearchDtoInterface $searchDto, ExtSystem $extSystem): array
    {
        if ($searchDto->getText()) {
            return [
                'multi_match' => [
                    'query' => $searchDto->getText(),
                    'type' => 'most_fields',
                    'fields' => [
                        'name',
                        'name.edgegrams',
                    ],
                ],
            ];
        }

        return parent::getMust($searchDto, $extSystem);
    }

    /**
     * @param KeywordAdmSearchDto $searchDto
     * @psalm-suppress PossiblyNullReference
     */
    protected function getFilter(SearchDtoInterface $searchDto, ExtSystem $extSystem): array
    {
        $filter = [];

        if (false === (empty($searchDto->getId()))) {
            $filter[] = ['terms' => ['id' => explode(',', $searchDto->getId())]];
        }
        if (false === (null === $searchDto->isReviewed())) {
            $filter[] = ['terms' => ['reviewed' => [$searchDto->isReviewed()]]];
        }

        return $filter;
    }
}
