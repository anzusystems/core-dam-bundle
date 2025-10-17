<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Elasticsearch\QueryFactory;

use AnzuSystems\CommonBundle\Domain\Job\JobProcessor;
use AnzuSystems\CommonBundle\Entity\Job;
use AnzuSystems\CommonBundle\Entity\JobUserDataDelete;
use AnzuSystems\CommonBundle\Model\Enum\JobStatus;
use AnzuSystems\CommonBundle\Tests\AnzuKernelTestCase;
use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures as BaseAssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\PodcastFixtures;
use AnzuSystems\CoreDamBundle\Domain\Job\Processor\JobPodcastSynchronizerProcessor;
use AnzuSystems\CoreDamBundle\Domain\Job\Processor\JobUserDataDeleteProcessor;
use AnzuSystems\CoreDamBundle\Domain\Podcast\PodcastRssReader;
use AnzuSystems\CoreDamBundle\Elasticsearch\QueryFactory\AssetQueryFactory;
use AnzuSystems\CoreDamBundle\Elasticsearch\QueryFactory\AuthorQueryFactory;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\AssetAdmSearchDto;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\AuthorAdmSearchDto;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\JobPodcastSynchronizer;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Repository\ExtSystemRepository;
use AnzuSystems\CoreDamBundle\Repository\PodcastRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\JobFixtures;
use AnzuSystems\CoreDamBundle\Tests\HttpClient\RssPodcastMock;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
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

    /**
     * @dataProvider buildQueryDataProvider
     */
    public function testBuildQuery(AuthorAdmSearchDto $searchDto, array $expectedQuery, array $expectedSort): void
    {
        $extSystem = $this->extSystemRepository->findOneBy(['slug' => 'cms']);
        $query = $this->authorQueryFactory->buildQuery($searchDto, $extSystem);

        $this->assertEqualsCanonicalizing($expectedQuery, $query['body']['query']);
        $this->assertEqualsCanonicalizing($expectedSort, $query['body']['sort']);
    }

    public function buildQueryDataProvider(): array
    {
        return [
            'test_score_date_no_fulltext' => [
                'searchDto' => (new AuthorAdmSearchDto())->setOrder(['score_date' => 'asc']),
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
                'searchDto' => (new AuthorAdmSearchDto())->setText('test')->setOrder(['score_date' => 'asc']),
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
                'searchDto' => (new AuthorAdmSearchDto())->setText('test')->setOrder(['score_best' => 'desc']),
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
                'searchDto' => (new AuthorAdmSearchDto())->setText('test')->setOrder(['id' => 'desc']),
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
        ];
    }
}
