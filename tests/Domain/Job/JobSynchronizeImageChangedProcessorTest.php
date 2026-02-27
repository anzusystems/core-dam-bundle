<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Job;

use AnzuSystems\CommonBundle\Model\Enum\JobStatus;
use AnzuSystems\CoreDamBundle\Domain\Job\Processor\JobSynchronizeImageChangedProcessor;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\JobSynchronizeImageChanged;
use AnzuSystems\CoreDamBundle\Model\ValueObject\JobSynchronizeImageChangedResult;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures;
use DateTimeImmutable;

final class JobSynchronizeImageChangedProcessorTest extends CoreDamKernelTestCase
{
    private JobSynchronizeImageChangedProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = $this->getService(JobSynchronizeImageChangedProcessor::class);
    }

    public function testProcessFailsWhenLicenceNotFound(): void
    {
        $job = $this->entityManager->getRepository(JobSynchronizeImageChanged::class)->findAll()[0];
        $this->assertInstanceOf(JobSynchronizeImageChanged::class, $job);

        $job->setTargetLicenceId(999_999);

        $this->processor->process($job);

        $this->assertSame(JobStatus::Error, $job->getStatus());
        $this->assertStringContainsStringIgnoringCase('not found', $job->getResult());
    }

    public function testProcessNotifiesImagesSuccessfully(): void
    {
        $job = $this->entityManager->getRepository(JobSynchronizeImageChanged::class)->findAll()[0];
        $this->assertInstanceOf(JobSynchronizeImageChanged::class, $job);

        $this->processor->process($job);

        $this->assertSame(JobStatus::Done, $job->getStatus());

        $result = JobSynchronizeImageChangedResult::fromString($job->getResult());
        $this->assertGreaterThan(0, $result->getNotifiedCount());
        $this->assertSame($result->getNotifiedCount(), $result->getTotalCount());
    }

    public function testProcessRespectsBulkSize(): void
    {
        $job = $this->entityManager->getRepository(JobSynchronizeImageChanged::class)->findAll()[0];
        $this->assertInstanceOf(JobSynchronizeImageChanged::class, $job);
        $job->setBulkSize(10);
        $jobId = $job->getId();

        $this->processor->process($job);

        // Re-fetch after EM clear
        $job = $this->entityManager->find(JobSynchronizeImageChanged::class, $jobId);

        $result = JobSynchronizeImageChangedResult::fromString($job->getResult());
        $this->assertGreaterThan(0, $result->getTotalCount());
    }

    public function testProcessFromFiltersFutureDate(): void
    {
        $job = $this->entityManager->getRepository(JobSynchronizeImageChanged::class)->findAll()[0];
        $this->assertInstanceOf(JobSynchronizeImageChanged::class, $job);
        $job->setProcessFrom(new DateTimeImmutable('+10 years'));

        $this->processor->process($job);

        $this->assertSame(JobStatus::Done, $job->getStatus());

        $result = JobSynchronizeImageChangedResult::fromString($job->getResult());
        $this->assertSame(0, $result->getNotifiedCount());
        $this->assertSame(0, $result->getTotalCount());
    }

    public function testProcessFromWithPastDateProcessesAllImages(): void
    {
        $job = $this->entityManager->getRepository(JobSynchronizeImageChanged::class)->findAll()[0];
        $this->assertInstanceOf(JobSynchronizeImageChanged::class, $job);
        $job->setProcessFrom(new DateTimeImmutable('2000-01-01'));

        $this->processor->process($job);

        $this->assertSame(JobStatus::Done, $job->getStatus());

        $result = JobSynchronizeImageChangedResult::fromString($job->getResult());
        $this->assertGreaterThan(0, $result->getNotifiedCount());
        $this->assertSame($result->getNotifiedCount(), $result->getTotalCount());
    }

    public function testBatchProcessingWithSmallBulkSize(): void
    {
        $job = $this->entityManager->getRepository(JobSynchronizeImageChanged::class)->findAll()[0];
        $this->assertInstanceOf(JobSynchronizeImageChanged::class, $job);
        $job->setBulkSize(1);
        $jobId = $job->getId();

        // First batch
        $this->processor->process($job);
        $job = $this->entityManager->find(JobSynchronizeImageChanged::class, $jobId);
        $this->assertSame(JobStatus::AwaitingBatchProcess, $job->getStatus());
        $this->assertSame(1, $job->getBatchProcessedIterationCount());

        // Second batch
        $this->processor->process($job);
        $job = $this->entityManager->find(JobSynchronizeImageChanged::class, $jobId);
        $this->assertSame(JobStatus::AwaitingBatchProcess, $job->getStatus());
        $this->assertSame(2, $job->getBatchProcessedIterationCount());

        // Continue until done
        $maxIterations = 20;
        $iteration = 2;
        while (JobStatus::AwaitingBatchProcess === $job->getStatus() && $iteration < $maxIterations) {
            $this->processor->process($job);
            $job = $this->entityManager->find(JobSynchronizeImageChanged::class, $jobId);
            $iteration++;
        }

        $this->assertSame(JobStatus::Done, $job->getStatus());

        $result = JobSynchronizeImageChangedResult::fromString($job->getResult());
        $this->assertGreaterThan(0, $result->getTotalCount());
    }
}
