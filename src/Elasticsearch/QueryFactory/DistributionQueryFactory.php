<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\QueryFactory;

use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\DistributionAdmSearchDto;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\SearchDtoInterface;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;

final class DistributionQueryFactory extends AbstractQueryFactory
{
    public function getSupportedSearchDtoClasses(): array
    {
        return [
            DistributionAdmSearchDto::class,
        ];
    }

    /**
     * @param DistributionAdmSearchDto $searchDto
     * @psalm-suppress PossiblyNullReference
     */
    protected function getFilter(SearchDtoInterface $searchDto, ExtSystem $extSystem): array
    {
        $filter = [];

        if (false === (null === $searchDto->getService())) {
            $filter[] = ['terms' => ['service' => [$searchDto->getService()]]];
        }
        if (false === (null === $searchDto->getServiceSlug())) {
            $filter[] = ['terms' => ['serviceSlug' => [$searchDto->getServiceSlug()]]];
        }
        if (false === (null === $searchDto->getStatus())) {
            $filter[] = ['terms' => ['status' => [$searchDto->getStatus()->toString()]]];
        }
        if (false === (null === $searchDto->getExtId())) {
            $filter[] = ['terms' => ['extId' => [$searchDto->getExtId()]]];
        }

        if (false === $searchDto->getLicences()->isEmpty()) {
            $terms = [];
            foreach ($searchDto->getLicences() as $licenceId) {
                $terms[] = ['term' => ['licenceId' => $licenceId->getId()]];
            }

            $filter[] = [
                'bool' => [
                    'should' => $terms,
                ],
            ];
        }

        return $filter;
    }
}
