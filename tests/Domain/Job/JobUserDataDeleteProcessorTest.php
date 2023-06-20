<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Job;

use AnzuSystems\CommonBundle\Domain\Job\JobProcessor;
use AnzuSystems\CommonBundle\Entity\Job;
use AnzuSystems\CommonBundle\Entity\JobUserDataDelete;
use AnzuSystems\CommonBundle\Model\Enum\JobStatus;
use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures as BaseAssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Domain\Job\Processor\JobUserDataDeleteProcessor;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\JobFixtures;

final class JobUserDataDeleteProcessorTest extends CoreDamKernelTestCase
{
    private JobProcessor $jobProcessor;
    private JobUserDataDeleteProcessor $jobUserDataDeleteProcessor;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var JobProcessor $jobProcessor */
        $jobProcessor = self::getContainer()->get(JobProcessor::class);
        $this->jobProcessor = $jobProcessor;

        /** @var JobUserDataDeleteProcessor $jobUserDataDeleteProcessor */
        $jobUserDataDeleteProcessor = self::getContainer()->get(JobUserDataDeleteProcessor::class);
        $this->jobUserDataDeleteProcessor = $jobUserDataDeleteProcessor;
    }

    public function testProcess(): void
    {
        $this->jobUserDataDeleteProcessor->setBulkSize(1);

        // 1. Process the first bulk
        $this->jobProcessor->process();
        $job = $this->entityManager->find(Job::class, JobFixtures::ID_DELETE_BLOG_USER_JOB);
        $this->assertInstanceOf(JobUserDataDelete::class, $job);

        $this->assertSame(JobStatus::AwaitingBatchProcess, $job->getStatus());
        $this->assertSame(1, $job->getBatchProcessedIterationCount());

        // 2. Process the second bulk
        $this->jobProcessor->process();
        $job = $this->entityManager->find(Job::class, JobFixtures::ID_DELETE_BLOG_USER_JOB);
        $this->assertInstanceOf(JobUserDataDelete::class, $job);
        $this->assertSame(JobStatus::AwaitingBatchProcess, $job->getStatus());
        $this->assertSame(2, $job->getBatchProcessedIterationCount());

        // 3. Process the third bulk
        $this->jobProcessor->process();
        $job = $this->entityManager->find(Job::class, JobFixtures::ID_DELETE_BLOG_USER_JOB);
        $this->assertInstanceOf(JobUserDataDelete::class, $job);
        $this->assertSame(2, $job->getBatchProcessedIterationCount());
        $this->assertSame(JobStatus::Done, $job->getStatus());

        // 4. Check if user anonymized
        $user = $this->entityManager->find(AnzuUser::class, $job->getTargetUserId());
        $this->assertInstanceOf(User::class, $user);
        $this->assertCount(0, $user->getAssetLicences());
        $this->assertStringStartsWith('deleted_', $user->getEmail());

        // 5. Check if licence for blog deleted but cms kept untouched
        $blogLicence = $this->entityManager->find(AssetLicence::class, AssetLicenceFixtures::LICENCE_ID);
        $this->assertNull($blogLicence);
        $cmsLicence = $this->entityManager->find(AssetLicence::class, BaseAssetLicenceFixtures::DEFAULT_LICENCE_ID);
        $this->assertInstanceOf(AssetLicence::class, $cmsLicence);
        /** @var AssetRepository $assetRepository */
        $assetRepository = self::getContainer()->get(AssetRepository::class);
        $assets = $assetRepository->geAllByLicenceIds([BaseAssetLicenceFixtures::DEFAULT_LICENCE_ID], 1);
        $this->assertNotEmpty($assets);
    }
}
