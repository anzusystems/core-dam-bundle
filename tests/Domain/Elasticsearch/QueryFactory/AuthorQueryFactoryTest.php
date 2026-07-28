<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Elasticsearch\QueryFactory;

use AnzuSystems\CoreDamBundle\Elasticsearch\QueryFactory\AuthorQueryFactory;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\AuthorAdmSearchDto;
use AnzuSystems\CoreDamBundle\Repository\ExtSystemRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

final class AuthorQueryFactoryTest extends CoreDamKernelTestCase
{
    private AuthorQueryFactory $authorQueryFactory;
    private ExtSystemRepository $extSystemRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authorQueryFactory = $this->getService(AuthorQueryFactory::class);
        $this->extSystemRepository = $this->getService(ExtSystemRepository::class);
    }

    #[DataProvider('buildQueryDataProvider')]
    public function testBuildQuery(AuthorAdmSearchDto $searchDto, array $expectedQuery, array $expectedSort): void
    {
        $extSystem = $this->extSystemRepository->findOneBy(['slug' => 'cms']);
        $query = $this->authorQueryFactory->buildQuery($searchDto, $extSystem);

        $this->assertEqualsCanonicalizing($expectedQuery, $query['body']['query']);
        $this->assertEqualsCanonicalizing($expectedSort, $query['body']['sort']);
    }

    public static function buildQueryDataProvider(): array
    {
        return [
            'test_score_date_no_fulltext' => [
                'searchDto' => (new AuthorAdmSearchDto())->setOrder(['score_date' => 'asc']),
                'expectedQuery' => self::expectedMatchAllQuery(),
                'expectedSort' => [
                    'id' => 'asc',
                ],
            ],
            'test_score_date' => [
                'searchDto' => (new AuthorAdmSearchDto())->setText('test')->setOrder(['score_date' => 'asc']),
                'expectedQuery' => self::expectedFulltextQuery(),
                'expectedSort' => [
                    'id' => 'asc',
                ],
            ],
            'test_score_best' => [
                'searchDto' => (new AuthorAdmSearchDto())->setText('test')->setOrder(['score_best' => 'desc']),
                'expectedQuery' => self::expectedFulltextQuery(),
                'expectedSort' => [
                    '_score' => 'desc',
                ],
            ],
            'test_id' => [
                'searchDto' => (new AuthorAdmSearchDto())->setText('test')->setOrder(['id' => 'desc']),
                'expectedQuery' => self::expectedFulltextQuery(),
                'expectedSort' => [
                    'id' => 'desc',
                ],
            ],
            'no_order_fulltext' => [
                'searchDto' => (new AuthorAdmSearchDto())->setText('test'),
                'expectedQuery' => self::expectedFulltextQuery(),
                'expectedSort' => [
                    '_score' => 'desc',
                    'id' => 'desc',
                ],
            ],
            'no_order_no_fulltext' => [
                'searchDto' => (new AuthorAdmSearchDto()),
                'expectedQuery' => self::expectedMatchAllQuery(),
                'expectedSort' => [
                    'reviewed' => 'desc',
                    'id' => 'desc',
                ],
            ],
        ];
    }

    private static function expectedFulltextQuery(): array
    {
        return [
            'bool' => [
                'must' => [
                    'bool' => [
                        'must' => [
                            'multi_match' => [
                                'query' => 'test',
                                'fields' => [
                                    'name^3',
                                    'name.edgegrams',
                                ],
                                'type' => 'most_fields',
                                'tie_breaker' => 0.3,
                            ],
                        ],
                        'should' => [
                            ['term' => ['reviewed' => ['value' => true, 'boost' => 5]]],
                        ],
                    ],
                ],
                'filter' => [],
                'must_not' => [],
            ],
        ];
    }

    private static function expectedMatchAllQuery(): array
    {
        return [
            'bool' => [
                'must' => [
                    'match_all' => new stdClass(),
                ],
                'filter' => [],
                'must_not' => [],
            ],
        ];
    }
}
