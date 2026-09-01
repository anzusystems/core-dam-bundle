<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Elasticsearch\QueryFactory;

use AnzuSystems\CoreDamBundle\Elasticsearch\QueryFactory\AssetQueryFactory;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\AssetAdmSearchDto;
use AnzuSystems\CoreDamBundle\Repository\ExtSystemRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

final class AssetQueryFactoryTest extends CoreDamKernelTestCase
{
    private AssetQueryFactory $assetQueryFactory;
    private ExtSystemRepository $extSystemRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assetQueryFactory = $this->getService(AssetQueryFactory::class);
        $this->extSystemRepository = $this->getService(ExtSystemRepository::class);
    }

    public function testUploadedAtRangeFilterIsApplied(): void
    {
        $extSystem = $this->extSystemRepository->findOneBy(['slug' => 'cms']);
        $searchDto = (new AssetAdmSearchDto())
            ->setUploadedAtFrom(new DateTimeImmutable('2026-01-01T00:00:00+00:00'))
            ->setUploadedAtUntil(new DateTimeImmutable('2026-01-31T23:59:59+00:00'));

        $query = $this->assetQueryFactory->buildQuery($searchDto, $extSystem);

        self::assertContains(
            [
                'range' => [
                    'uploadedAt' => [
                        'gte' => $searchDto->getUploadedAtFrom()?->getTimestamp(),
                        'lte' => $searchDto->getUploadedAtUntil()?->getTimestamp(),
                        'format' => 'epoch_second',
                    ],
                ],
            ],
            $query['body']['query']['bool']['filter'],
        );
    }

    #[DataProvider('buildQueryDataProvider')]
    public function testBuildQuery(AssetAdmSearchDto $searchDto, array $expectedQuery, array $expectedSort): void
    {
        $extSystem = $this->extSystemRepository->findOneBy(['slug' => 'cms']);
        $query = $this->assetQueryFactory->buildQuery($searchDto, $extSystem);

        $this->assertEqualsCanonicalizing($expectedQuery, $query['body']['query']);
        $this->assertEqualsCanonicalizing($expectedSort, $query['body']['sort']);
    }

    public static function buildQueryDataProvider(): array
    {
        return [
            'test_score_date_no_fulltext' => [
                'searchDto' => (new AssetAdmSearchDto())->setOrder(['score_date' => 'desc']),
                'expectedQuery' =>
                    [
                        'bool' => [
                            'must' => [
                                'match_all' => new stdClass(),
                            ],
                            'filter' => [],
                            'must_not' => [],
                        ],
                    ],
                'expectedSort' => [
                    'createdAt' => 'desc',
                ],
            ],
            'test_score_date' => [
                'searchDto' => (new AssetAdmSearchDto())->setText('test')->setOrder(['score_date' => 'asc']),
                'expectedQuery' =>
                    [
                        'function_score' => [
                            'query' => [
                                'bool' => [
                                    'must' => [
                                        'bool' => [
                                            'should' => [
                                                [
                                                    'multi_match' => [
                                                        'query' => 'test',
                                                        'fields' => [
                                                            'custom_data_title^5',
                                                            'custom_data_title.edgegrams^1',
                                                            'custom_data_title.lang^1',
                                                            'custom_data_headline',
                                                            'keywordNames^2',
                                                            'keywordNames.lang^1',
                                                            'authorNames^2',
                                                            'authorNames.lang^1',
                                                        ],
                                                        'type' => 'most_fields',
                                                        'tie_breaker' => 0.3,
                                                        'lenient' => true,
                                                    ],
                                                ],
                                                [
                                                    'multi_match' => [
                                                        'query' => 'test',
                                                        'fields' => [
                                                            'custom_data_title^5',
                                                            'custom_data_title.lang^1',
                                                            'custom_data_headline',
                                                            'keywordNames^2',
                                                            'keywordNames.lang^1',
                                                            'authorNames^2',
                                                            'authorNames.lang^1',
                                                        ],
                                                        'type' => 'most_fields',
                                                        'tie_breaker' => 0.3,
                                                        'lenient' => true,
                                                        'fuzziness' => 'AUTO',
                                                        'prefix_length' => 2,
                                                        'max_expansions' => 50,
                                                        'boost' => 0.5,
                                                    ],
                                                ],
                                            ],
                                            'minimum_should_match' => 1,
                                        ],
                                    ],
                                    'filter' => [],
                                    'must_not' => [],
                                ],
                            ],
                            'functions' => [
                                [
                                    'exp' => [
                                        'createdAt' => [
                                            'origin' => 'now',
                                            'scale' => '60d',
                                            'offset' => '14d',
                                            'decay' => 0.5,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                'expectedSort' => [
                    '_score' => 'asc',
                ],
            ],
            'test_score_best' => [
                'searchDto' => (new AssetAdmSearchDto())->setText('test')->setOrder(['score_best' => 'desc']),
                'expectedQuery' =>
                    [
                        'bool' => [
                            'must' => [
                                'bool' => [
                                    'should' => [
                                        [
                                            'multi_match' => [
                                                'query' => 'test',
                                                'fields' => [
                                                    'custom_data_title^5',
                                                    'custom_data_title.edgegrams^1',
                                                    'custom_data_title.lang^1',
                                                    'custom_data_headline',
                                                    'keywordNames^2',
                                                    'keywordNames.lang^1',
                                                    'authorNames^2',
                                                    'authorNames.lang^1',
                                                ],
                                                'type' => 'most_fields',
                                                'tie_breaker' => 0.3,
                                                'lenient' => true,
                                            ],
                                        ],
                                        [
                                            'multi_match' => [
                                                'query' => 'test',
                                                'fields' => [
                                                    'custom_data_title^5',
                                                    'custom_data_title.lang^1',
                                                    'custom_data_headline',
                                                    'keywordNames^2',
                                                    'keywordNames.lang^1',
                                                    'authorNames^2',
                                                    'authorNames.lang^1',
                                                ],
                                                'type' => 'most_fields',
                                                'tie_breaker' => 0.3,
                                                'lenient' => true,
                                                'fuzziness' => 'AUTO',
                                                'prefix_length' => 2,
                                                'max_expansions' => 50,
                                                'boost' => 0.5,
                                            ],
                                        ],
                                    ],
                                    'minimum_should_match' => 1,
                                ],
                            ],
                            'filter' => [],
                            'must_not' => [],
                        ],
                    ],
                'expectedSort' => [
                    '_score' => 'desc',
                ],
            ],
            'test_id' => [
                'searchDto' => (new AssetAdmSearchDto())->setText('test')->setOrder(['id' => 'desc']),
                'expectedQuery' =>
                    [
                        'bool' => [
                            'must' => [
                                'bool' => [
                                    'should' => [
                                        [
                                            'multi_match' => [
                                                'query' => 'test',
                                                'fields' => [
                                                    'custom_data_title^5',
                                                    'custom_data_title.edgegrams^1',
                                                    'custom_data_title.lang^1',
                                                    'custom_data_headline',
                                                    'keywordNames^2',
                                                    'keywordNames.lang^1',
                                                    'authorNames^2',
                                                    'authorNames.lang^1',
                                                ],
                                                'type' => 'most_fields',
                                                'tie_breaker' => 0.3,
                                                'lenient' => true,
                                            ],
                                        ],
                                        [
                                            'multi_match' => [
                                                'query' => 'test',
                                                'fields' => [
                                                    'custom_data_title^5',
                                                    'custom_data_title.lang^1',
                                                    'custom_data_headline',
                                                    'keywordNames^2',
                                                    'keywordNames.lang^1',
                                                    'authorNames^2',
                                                    'authorNames.lang^1',
                                                ],
                                                'type' => 'most_fields',
                                                'tie_breaker' => 0.3,
                                                'lenient' => true,
                                                'fuzziness' => 'AUTO',
                                                'prefix_length' => 2,
                                                'max_expansions' => 50,
                                                'boost' => 0.5,
                                            ],
                                        ],
                                    ],
                                    'minimum_should_match' => 1,
                                ],
                            ],
                            'filter' => [],
                            'must_not' => [],
                        ],
                    ],
                'expectedSort' => [
                    'id' => 'desc',
                ],
            ],
            'default_order_fulltext' => [
                'searchDto' => (new AssetAdmSearchDto())->setText('test'),
                'expectedQuery' =>
                    [
                        'bool' => [
                            'must' => [
                                'bool' => [
                                    'should' => [
                                        [
                                            'multi_match' => [
                                                'query' => 'test',
                                                'fields' => [
                                                    'custom_data_title^5',
                                                    'custom_data_title.edgegrams^1',
                                                    'custom_data_title.lang^1',
                                                    'custom_data_headline',
                                                    'keywordNames^2',
                                                    'keywordNames.lang^1',
                                                    'authorNames^2',
                                                    'authorNames.lang^1',
                                                ],
                                                'type' => 'most_fields',
                                                'tie_breaker' => 0.3,
                                                'lenient' => true,
                                            ],
                                        ],
                                        [
                                            'multi_match' => [
                                                'query' => 'test',
                                                'fields' => [
                                                    'custom_data_title^5',
                                                    'custom_data_title.lang^1',
                                                    'custom_data_headline',
                                                    'keywordNames^2',
                                                    'keywordNames.lang^1',
                                                    'authorNames^2',
                                                    'authorNames.lang^1',
                                                ],
                                                'type' => 'most_fields',
                                                'tie_breaker' => 0.3,
                                                'lenient' => true,
                                                'fuzziness' => 'AUTO',
                                                'prefix_length' => 2,
                                                'max_expansions' => 50,
                                                'boost' => 0.5,
                                            ],
                                        ],
                                    ],
                                    'minimum_should_match' => 1,
                                ],
                            ],
                            'filter' => [],
                            'must_not' => [],
                        ],
                    ],
                'expectedSort' => [
                    '_score' => 'desc',
                ],
            ],
            'default_order_no_fulltext' => [
                'searchDto' => (new AssetAdmSearchDto()),
                'expectedQuery' =>
                    [
                        'bool' => [
                            'must' => [
                                'match_all' => new stdClass(),
                            ],
                            'filter' => [],
                            'must_not' => [],
                        ],
                    ],
                'expectedSort' => [
                    '_score' => 'desc',
                ],
            ],
        ];
    }
}
