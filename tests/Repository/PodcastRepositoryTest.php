<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Repository;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\DataFixtures\PodcastFixtures;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Model\Enum\PodcastImportMode;
use AnzuSystems\CoreDamBundle\Repository\PodcastRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class PodcastRepositoryTest extends CoreDamKernelTestCase
{
    private PodcastRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->getService(PodcastRepository::class);
    }

    #[DataProvider('firstPodcastToImportDataProvider')]
    public function testFindOneFromSkipsPodcastWithoutRssUrl(string $firstPodcastRssUrl, string $expectedPodcastId): void
    {
        $this->prepareFirstPodcastRssUrl($firstPodcastRssUrl);

        $found = $this->repository->findOneFrom(mode: PodcastImportMode::Import);

        self::assertInstanceOf(Podcast::class, $found);
        self::assertSame($expectedPodcastId, (string) $found->getId());
    }

    public static function firstPodcastToImportDataProvider(): array
    {
        return [
            'podcast with an rss url is imported first' => [
                'firstPodcastRssUrl' => 'https://anchor.fm/s/7758ecd4/podcast/rss',
                'expectedPodcastId' => PodcastFixtures::PODCAST_3,
            ],
            'podcast with an empty rss url is skipped' => [
                'firstPodcastRssUrl' => App::EMPTY_STRING,
                'expectedPodcastId' => PodcastFixtures::PODCAST_1,
            ],
        ];
    }

    private function prepareFirstPodcastRssUrl(string $rssUrl): void
    {
        $firstPodcast = $this->repository->find(PodcastFixtures::PODCAST_3);
        self::assertInstanceOf(Podcast::class, $firstPodcast);
        $firstPodcast->getAttributes()->setRssUrl($rssUrl);
        $this->entityManager->flush();
    }
}
