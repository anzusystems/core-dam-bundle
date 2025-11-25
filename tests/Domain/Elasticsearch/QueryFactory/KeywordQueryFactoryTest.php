<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Elasticsearch\QueryFactory;

use AnzuSystems\CoreDamBundle\Elasticsearch\QueryFactory\KeywordQueryFactory;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\KeywordAdmSearchDto;
use AnzuSystems\CoreDamBundle\Repository\ExtSystemRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

final class KeywordQueryFactoryTest extends CoreDamKernelTestCase
{
    private KeywordQueryFactory $keywordQueryFactory;
    private ExtSystemRepository $extSystemRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->keywordQueryFactory = $this->getService(KeywordQueryFactory::class);
        $this->extSystemRepository = $this->getService(ExtSystemRepository::class);
    }

    #[DataProvider('buildQueryDataProvider')]
    public function testBuildQuery(KeywordAdmSearchDto $searchDto, array $expectedQuery, array $expectedSort): void
    {
        $extSystem = $this->extSystemRepository->findOneBy(['slug' => 'cms']);
        $query = $this->keywordQueryFactory->buildQuery($searchDto, $extSystem);

        $this->assertEqualsCanonicalizing($expectedQuery, $query['body']['query']);
        $this->assertEqualsCanonicalizing($expectedSort, $query['body']['sort']);
    }

    public static function buildQueryDataProvider(): array
    {
        return [
            'test_score_date_no_fulltext' => [
                'searchDto' => (new KeywordAdmSearchDto())->setOrder(['score_date' => 'asc']),
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
                'searchDto' => (new KeywordAdmSearchDto())->setText('test')->setOrder(['score_date' => 'asc']),
                'expectedQuery' =>
                    [
                        'bool' => [
                            'must' => [
                                'multi_match' => [
                                    'query' => 'test',
                                    'fields' => [
                                        'name^3',
                                        'name.edgegrams',
                                    ],
                                    'type' => 'most_fields',
                                    'tie_breaker' => 0.3
                                ]
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
            'test_score_best' => [
                'searchDto' => (new KeywordAdmSearchDto())->setText('test')->setOrder(['score_best' => 'desc']),
                'expectedQuery' =>
                    [
                        'bool' => [
                            'must' => [
                                'multi_match' => [
                                    'query' => 'test',
                                    'fields' => [
                                        'name^3',
                                        'name.edgegrams',
                                    ],
                                    'type' => 'most_fields',
                                    'tie_breaker' => 0.3
                                ]
                            ],
                            'filter' => [],
                            'must_not' => []
                        ]
                    ]
                ,
                'expectedSort' => [
                    '_score' => 'desc',
                ],
            ],
            'test_id' => [
                'searchDto' => (new KeywordAdmSearchDto())->setText('test')->setOrder(['id' => 'desc']),
                'expectedQuery' =>
                    [
                        'bool' => [
                            'must' => [
                                'multi_match' => [
                                    'query' => 'test',
                                    'fields' => [
                                        'name^3',
                                        'name.edgegrams',
                                    ],
                                    'type' => 'most_fields',
                                    'tie_breaker' => 0.3
                                ]
                            ],
                            'filter' => [],
                            'must_not' => []
                        ]
                    ]
                ,
                'expectedSort' => [
                    'id' => 'desc',
                ],
            ],
            'no_order_no_fulltext' => [
                'searchDto' => (new KeywordAdmSearchDto()),
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
                    '_score' => 'desc',
                ],
            ],
            'no_order_fulltext' => [
                'searchDto' => (new KeywordAdmSearchDto())->setText('test'),
                'expectedQuery' =>
                    [
                        'bool' => [
                            'must' => [
                                'multi_match' => [
                                    'query' => 'test',
                                    'fields' => [
                                        'name^3',
                                        'name.edgegrams',
                                    ],
                                    'type' => 'most_fields',
                                    'tie_breaker' => 0.3
                                ]
                            ],
                            'filter' => [],
                            'must_not' => []
                        ]
                    ]
                ,
                'expectedSort' => [
                    '_score' => 'desc',
                ],
            ],
        ];
    }
}
