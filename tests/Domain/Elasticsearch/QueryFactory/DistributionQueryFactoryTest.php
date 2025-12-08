<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Elasticsearch\QueryFactory;

use AnzuSystems\CoreDamBundle\Elasticsearch\QueryFactory\DistributionQueryFactory;
use AnzuSystems\CoreDamBundle\Elasticsearch\QueryFactory\KeywordQueryFactory;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\DistributionAdmSearchDto;
use AnzuSystems\CoreDamBundle\Repository\ExtSystemRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

final class DistributionQueryFactoryTest extends CoreDamKernelTestCase
{
    private DistributionQueryFactory $distributionQueryFactory;
    private ExtSystemRepository $extSystemRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->distributionQueryFactory = $this->getService(DistributionQueryFactory::class);
        $this->extSystemRepository = $this->getService(ExtSystemRepository::class);
    }

    #[DataProvider('buildQueryDataProvider')]
    public function testBuildQuery(DistributionAdmSearchDto $searchDto, array $expectedQuery, array $expectedSort): void
    {
        $extSystem = $this->extSystemRepository->findOneBy(['slug' => 'cms']);
        $query = $this->distributionQueryFactory->buildQuery($searchDto, $extSystem);

        $this->assertEqualsCanonicalizing($expectedQuery, $query['body']['query']);
        $this->assertEqualsCanonicalizing($expectedSort, $query['body']['sort']);
    }

    public static function buildQueryDataProvider(): array
    {
        return [
            'test_score_date_no_fulltext' => [
                'searchDto' => (new DistributionAdmSearchDto())->setOrder(['score_date' => 'asc']),
                'expectedQuery' =>
                    [
                        'bool' => [
                            'must' => [
                                'match_all' => new stdClass()
                            ],
                            'filter' => [],
                            'must_not' => []
                        ]
                    ]
                ,
                'expectedSort' => [
                    'id' => 'asc',
                ],
            ],
            'test_score_date' => [
                'searchDto' => (new DistributionAdmSearchDto())->setText('test')->setOrder(['score_date' => 'asc']),
                'expectedQuery' =>
                    [
                        'bool' => [
                            'must' => [
                                'match_all' => new stdClass()
                            ],
                            'filter' => [],
                            'must_not' => []
                        ]
                    ]
                ,
                'expectedSort' => [
                    'id' => 'asc',
                ],
            ],
        ];
    }
}
