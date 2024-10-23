<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\QueryFactory;

use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\AuthorAdmSearchDto;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\SearchDtoInterface;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;

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
     * @param AuthorAdmSearchDto $searchDto
     * @psalm-suppress PossiblyNullReference
     */
    protected function getFilter(SearchDtoInterface $searchDto): array
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

        return $filter;
    }
}
