<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Job;

use AnzuSystems\CommonBundle\Domain\Job\JobProcessor;
use AnzuSystems\CommonBundle\Entity\Job;
use AnzuSystems\CommonBundle\Entity\JobUserDataDelete;
use AnzuSystems\CommonBundle\Model\Enum\JobStatus;
use AnzuSystems\CommonBundle\Tests\AnzuKernelTestCase;
use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures as BaseAssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Domain\Job\Processor\JobPodcastSynchronizerProcessor;
use AnzuSystems\CoreDamBundle\Domain\Job\Processor\JobUserDataDeleteProcessor;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\JobPodcastSynchronizer;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\JobFixtures;
use Doctrine\ORM\EntityManagerInterface;

final class JobPodcastSynchronizerProcessorTest extends CoreDamKernelTestCase
{
    private JobProcessor $jobProcessor;
    private JobPodcastSynchronizerProcessor $synchronizerProcessor;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var JobProcessor $jobProcessor */
        $jobProcessor = self::getContainer()->get(JobProcessor::class);
        $this->jobProcessor = $jobProcessor;

        /** @var JobUserDataDeleteProcessor $jobUserDataDeleteProcessor */

        $synchronizerProcessor = self::getContainer()->get(JobPodcastSynchronizerProcessor::class);
        $this->synchronizerProcessor = $synchronizerProcessor;
    }

    public function testProcess(): void
    {
        // 1. Process the first bulk
        $this->jobProcessor->process();
        $job = $this->entityManager->find(Job::class, JobFixtures::ID_PODCAST_SYNCHRONYZER_JOB);
        $this->assertInstanceOf(JobPodcastSynchronizer::class, $job);

        $job->setFullSync(true);
        dump($job->getStatus()->toString() . ' ' . $job->getLastBatchProcessedRecord());
        $this->synchronizerProcessor->process($job);
//        dump($job->isFullSync(), $job->getLastBatchProcessedRecord());
        dump($job->getStatus()->toString() . ' ' . $job->getLastBatchProcessedRecord());
        $this->synchronizerProcessor->process($job);
//        dump($job->isFullSync(), $job->getLastBatchProcessedRecord());
        dump($job->getStatus()->toString() . ' ' . $job->getLastBatchProcessedRecord());
        $this->synchronizerProcessor->process($job);
//        dump($job->isFullSync(), $job->getLastBatchProcessedRecord());
        dump($job->getStatus()->toString() . ' ' . $job->getLastBatchProcessedRecord());

        $this->synchronizerProcessor->process($job);
        dump($job->getStatus()->toString() . ' ' . $job->getLastBatchProcessedRecord());
        $this->synchronizerProcessor->process($job);
        dump($job->getStatus()->toString() . ' ' . $job->getLastBatchProcessedRecord());
        $this->synchronizerProcessor->process($job);
        dump($job->getStatus()->toString() . ' ' . $job->getLastBatchProcessedRecord());
        $this->synchronizerProcessor->process($job);
        dump($job->getStatus()->toString() . ' ' . $job->getLastBatchProcessedRecord());
        $this->synchronizerProcessor->process($job);
        dump($job->getStatus()->toString() . ' ' . $job->getLastBatchProcessedRecord());

//        dump($job);
    }
}
