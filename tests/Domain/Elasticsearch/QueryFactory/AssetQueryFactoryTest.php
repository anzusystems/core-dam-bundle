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
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\AssetAdmSearchDto;
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

    /**
     * @dataProvider buildQueryDataProvider
     */
    public function testBuildQuery(AssetAdmSearchDto $searchDto, array $expectedQuery, array $expectedSort): void
    {
        $extSystem = $this->extSystemRepository->findOneBy(['slug' => 'cms']);
        $query = $this->assetQueryFactory->buildQuery($searchDto, $extSystem);

        $this->assertEqualsCanonicalizing($expectedQuery, $query['body']['query']);
        $this->assertEqualsCanonicalizing($expectedSort, $query['body']['sort']);
    }

    public function buildQueryDataProvider(): array
    {
        return [
            'test_score_date_no_fulltext' => [
                'searchDto' => (new AssetAdmSearchDto())->setOrder(['score_date' => 'desc']),
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
                                        'multi_match' => [
                                            'query' => 'test',
                                            'fields' => [
                                                'custom_data_title^5',
                                                'custom_data_title.edgegrams^1',
                                                'custom_data_title.lang^1',
                                                'custom_data_headline'
                                            ],
                                            'type' => 'most_fields',
                                            'tie_breaker' => 0.3
                                        ]
                                    ],
                                    'filter' => [],
                                    'must_not' => []
                                ]
                            ],
                            'functions' => [
                                [
                                    'exp' => [
                                        'createdAt' => [
                                            'origin' => 'now',
                                            'scale' => '60d',
                                            'offset' => '14d',
                                            'decay' => 0.5
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ,
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
                                'multi_match' => [
                                    'query' => 'test',
                                    'fields' => [
                                        'custom_data_title^5',
                                        'custom_data_title.edgegrams^1',
                                        'custom_data_title.lang^1',
                                        'custom_data_headline'
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
                'searchDto' => (new AssetAdmSearchDto())->setText('test')->setOrder(['id' => 'desc']),
                'expectedQuery' =>
                    [
                        'bool' => [
                            'must' => [
                                'multi_match' => [
                                    'query' => 'test',
                                    'fields' => [
                                        'custom_data_title^5',
                                        'custom_data_title.edgegrams^1',
                                        'custom_data_title.lang^1',
                                        'custom_data_headline'
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
