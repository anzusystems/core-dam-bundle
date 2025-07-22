<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Job;

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
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\JobPodcastSynchronizer;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Repository\PodcastRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\JobFixtures;
use AnzuSystems\CoreDamBundle\Tests\HttpClient\RssPodcastMock;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;

final class JobPodcastSynchronizerProcessorTest extends CoreDamKernelTestCase
{
    private JobPodcastSynchronizerProcessor $synchronizerProcessor;
    private PodcastRepository $podcastRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->synchronizerProcessor = $this->getService(JobPodcastSynchronizerProcessor::class);
        $this->podcastRepository = $this->getService(PodcastRepository::class);
    }

    public function testFullSyncProcess(): void
    {
        $job = $this->entityManager->getRepository(JobPodcastSynchronizer::class)->findBy(['fullSync' => true])[0] ?? null;
        $this->assertInstanceOf(JobPodcastSynchronizer::class, $job);

        $this->synchronizerProcessor->setBulkSize(2);
        $job->setFullSync(true);

        $podcast1 = $this->podcastRepository->find(PodcastFixtures::PODCAST_1);
        $podcast2 = $this->podcastRepository->find(PodcastFixtures::PODCAST_2);
        $podcast3 = $this->podcastRepository->find(PodcastFixtures::PODCAST_3);

        $this->assertCount(2, $podcast1->getEpisodes());
        $this->assertCount(0, $podcast2->getEpisodes());
        $this->assertCount(0, $podcast3->getEpisodes());

        $this->synchronizerProcessor->process($job);
        $this->entityManager->refresh($podcast1);
        $this->entityManager->refresh($podcast3);
        $this->assertCount(3, $podcast1->getEpisodes());
        $this->assertCount(1, $podcast3->getEpisodes());
        $this->assertEquals(JobStatus::AwaitingBatchProcess, $job->getStatus());

        $pointerDate = App::getAppDate()->modify(RssPodcastMock::THIRD_RSS_DATE_MODEFIER)->format(DateTimeInterface::ATOM);
        $this->assertEquals(sprintf('%s|%s', PodcastFixtures::PODCAST_1, $pointerDate), $job->getLastBatchProcessedRecord());

        $this->synchronizerProcessor->process($job);
        $this->entityManager->refresh($podcast1);
        $this->assertCount(5, $podcast1->getEpisodes());
        $this->assertEquals(JobStatus::AwaitingBatchProcess, $job->getStatus());

        $pointerDate = App::getAppDate()->modify(RssPodcastMock::FIRST_RSS_DATE_MODEFIER)->format(DateTimeInterface::ATOM);
        $this->assertEquals(sprintf('%s|%s', PodcastFixtures::PODCAST_1, $pointerDate), $job->getLastBatchProcessedRecord());

        $this->synchronizerProcessor->process($job);
        $this->entityManager->refresh($podcast2);
        $this->assertCount(2, $podcast2->getEpisodes());
        $this->assertEquals(JobStatus::AwaitingBatchProcess, $job->getStatus());

        $pointerDate = App::getAppDate()->modify(RssPodcastMock::FIRST_RSS_DATE_MODEFIER)->format(DateTimeInterface::ATOM);
        $this->assertEquals(sprintf('%s|%s', PodcastFixtures::PODCAST_2, $pointerDate), $job->getLastBatchProcessedRecord());

        $this->synchronizerProcessor->process($job);
        $this->assertEquals(JobStatus::Done, $job->getStatus());
        $this->assertEquals('Podcast job finished. Imported 6 episodes.', $job->getResult());
    }

    public function testSpecificPodcastSyncProcess(): void
    {
        $job = $this->entityManager->getRepository(JobPodcastSynchronizer::class)->findBy(['fullSync' => false])[0];
        $this->assertInstanceOf(JobPodcastSynchronizer::class, $job);

        $this->synchronizerProcessor->setBulkSize(2);
        $podcast1 = $this->podcastRepository->find(PodcastFixtures::PODCAST_1);

        $this->synchronizerProcessor->process($job);
        $this->entityManager->refresh($podcast1);
        $this->assertCount(4, $podcast1->getEpisodes());
        $this->assertEquals(JobStatus::AwaitingBatchProcess, $job->getStatus());

        $this->synchronizerProcessor->process($job);
        $this->entityManager->refresh($podcast1);
        $this->assertCount(5, $podcast1->getEpisodes());
        $this->assertEquals(JobStatus::Done, $job->getStatus());
    }

    public function testSpecificPodcastSyncProcessAndImportFrom(): void
    {
        $job = $this->entityManager->getRepository(JobPodcastSynchronizer::class)->findBy(['fullSync' => false])[0];
        $this->assertInstanceOf(JobPodcastSynchronizer::class, $job);

        $this->synchronizerProcessor
            ->setBulkSize(2)
            ->setMinImportFrom(App::getAppDate()->modify('-7 weeks'))
        ;
        $podcast1 = $this->podcastRepository->find(PodcastFixtures::PODCAST_1);

        $this->synchronizerProcessor->process($job);
        $this->entityManager->refresh($podcast1);
        $this->assertCount(3, $podcast1->getEpisodes());
        $this->assertEquals(JobStatus::Done, $job->getStatus());
    }
}
