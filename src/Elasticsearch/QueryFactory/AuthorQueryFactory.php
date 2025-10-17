<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\QueryFactory;

use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\AuthorAdmSearchDto;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\SearchDtoInterface;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Helper\StringHelper;

final class AuthorQueryFactory extends AbstractQueryFactory
{
    public function getSupportedSearchDtoClasses(): array
    {
        return [
            AuthorAdmSearchDto::class,
        ];
    }

    /**
     * @param AuthorAdmSearchDto $searchDto
     */
    public function isFulltextSearch(SearchDtoInterface $searchDto): bool
    {
        return StringHelper::isNotEmpty($searchDto->getText());
    }

    /**
     * @param AuthorAdmSearchDto $searchDto
     */
    protected function getMust(SearchDtoInterface $searchDto, ExtSystem $extSystem): array
    {
        if ($this->isFulltextSearch($searchDto)) {
            return [
                'multi_match' => [
                    'query' => $searchDto->getText(),
                    'type' => 'most_fields',
                    'tie_breaker' => 0.3,
                    'fields' => [
                        'name^3',
                        'name.edgegrams',
                    ],
                ],
            ];
        }

        return parent::getMust($searchDto, $extSystem);
    }

    /**
     * @param AuthorAdmSearchDto $searchDto
     * @psalm-suppress PossiblyNullReference
     */
    protected function getFilter(SearchDtoInterface $searchDto, ExtSystem $extSystem): array
    {
        $filter = [];

        if (false === (empty($searchDto->getId()))) {
            $filter[] = ['terms' => ['id' => explode(',', $searchDto->getId())]];
        }
        if (false === (empty($searchDto->getIdentifier()))) {
            $filter[] = ['terms' => ['identifier' => [$searchDto->getIdentifier()]]];
        }
        if (false === (null === $searchDto->isReviewed())) {
            $filter[] = ['terms' => ['reviewed' => [$searchDto->isReviewed()]]];
        }
        if (false === (null === $searchDto->getType())) {
            $filter[] = ['terms' => ['type' => [$searchDto->getType()]]];
        }
        if (false === (null === $searchDto->isCanBeCurrentAuthor())) {
            $filter[] = ['term' => ['canBeCurrentAuthor' => $searchDto->isCanBeCurrentAuthor()]];
        }

        return $filter;
    }
}
