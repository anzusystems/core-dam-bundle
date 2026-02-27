<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Job;

use AnzuSystems\CommonBundle\Model\Enum\JobStatus;
use AnzuSystems\CoreDamBundle\Domain\Job\Processor\JobAssetFileReprocessInternalFlagProcessor;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\JobAssetFileReprocessInternalFlag;
use AnzuSystems\CoreDamBundle\Model\ValueObject\JobAssetFileReprocessInternalFlagResult;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ImageFixtures;
use DateTimeImmutable;

final class JobAssetFileReprocessInternalFlagProcessorTest extends CoreDamKernelTestCase
{
    private JobAssetFileReprocessInternalFlagProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = $this->getService(JobAssetFileReprocessInternalFlagProcessor::class);
    }

    public function testProcessFailsWhenLicenceNotFound(): void
    {
        $job = $this->entityManager->getRepository(JobAssetFileReprocessInternalFlag::class)->findAll()[0];
        $this->assertInstanceOf(JobAssetFileReprocessInternalFlag::class, $job);

        $job->setTargetLicenceId(999_999);

        $this->processor->process($job);

        $this->assertSame(JobStatus::Error, $job->getStatus());
        $this->assertStringContainsStringIgnoringCase('not found', $job->getResult());
    }

    public function testProcessFailsWhenInternalRuleInactive(): void
    {
        $job = $this->entityManager->getRepository(JobAssetFileReprocessInternalFlag::class)->findAll()[0];
        $this->assertInstanceOf(JobAssetFileReprocessInternalFlag::class, $job);

        // The fixture licence has an inactive internal rule by default (active = false).
        $this->processor->process($job);

        $this->assertSame(JobStatus::Error, $job->getStatus());
        $this->assertStringContainsStringIgnoringCase('not active', $job->getResult());
    }

    public function testProcessSetsInternalFlagOnAssetFiles(): void
    {
        // Activate the internal rule for the fixture licence.
        /** @var AssetLicence $licence */
        $licence = $this->entityManager->find(AssetLicence::class, AssetLicenceFixtures::LICENCE_ID);
        $licence->getInternalRule()->setActive(true);

        // Set IMAGE_ID_1 and IMAGE_ID_2 internal flag to false so they will be changed.
        /** @var ImageFile $image1 */
        $image1 = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1);
        $image1->getFlags()->setInternal(false);

        /** @var ImageFile $image2 */
        $image2 = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_2);
        $image2->getFlags()->setInternal(false);

        $this->entityManager->flush();

        $job = $this->entityManager->getRepository(JobAssetFileReprocessInternalFlag::class)->findAll()[0];
        $this->assertInstanceOf(JobAssetFileReprocessInternalFlag::class, $job);

        $this->processor->process($job);

        $this->assertSame(JobStatus::Done, $job->getStatus());

        $result = JobAssetFileReprocessInternalFlagResult::fromString($job->getResult());
        // IMAGE_ID_1 and IMAGE_ID_2 flipped from false to true.
        $this->assertSame(2, $result->getChangedCount());
        // All 3 files under LICENCE_ID were processed (IMAGE_ID_1, IMAGE_ID_2, IMAGE_ID_2_1).
        $this->assertSame(3, $result->getTotalCount());

        // Re-fetch and assert all three images are now internal.
        $this->entityManager->clear();

        /** @var ImageFile $image1Refetched */
        $image1Refetched = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1);
        $this->assertTrue($image1Refetched->getFlags()->isInternal());

        /** @var ImageFile $image2Refetched */
        $image2Refetched = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_2);
        $this->assertTrue($image2Refetched->getFlags()->isInternal());
    }

    public function testProcessRespectsBatchSize(): void
    {
        // Activate the internal rule for the fixture licence.
        /** @var AssetLicence $licence */
        $licence = $this->entityManager->find(AssetLicence::class, AssetLicenceFixtures::LICENCE_ID);
        $licence->getInternalRule()->setActive(true);
        $this->entityManager->flush();

        $job = $this->entityManager->getRepository(JobAssetFileReprocessInternalFlag::class)->findAll()[0];
        $this->assertInstanceOf(JobAssetFileReprocessInternalFlag::class, $job);
        $job->setBulkSize(1);
        $jobId = $job->getId();

        // First batch: processes Asset1 (1 slot). bulkSize=1 matches → AwaitingBatchProcess.
        $this->processor->process($job);
        // The processor clears the EM, so re-fetch the job to read the updated state.
        $job = $this->entityManager->find(JobAssetFileReprocessInternalFlag::class, $jobId);
        $this->assertSame(JobStatus::AwaitingBatchProcess, $job->getStatus());
        $this->assertSame(1, $job->getBatchProcessedIterationCount());

        // Second batch: processes Asset2 (2 slots). bulkSize=1 matches → AwaitingBatchProcess.
        $this->processor->process($job);
        $job = $this->entityManager->find(JobAssetFileReprocessInternalFlag::class, $jobId);
        $this->assertSame(JobStatus::AwaitingBatchProcess, $job->getStatus());
        $this->assertSame(2, $job->getBatchProcessedIterationCount());

        // Third batch: no more assets → Done.
        // There are 2 assets under LICENCE_ID (Asset1, Asset2), so after 2 batches everything is processed.
        $this->processor->process($job);
        $job = $this->entityManager->find(JobAssetFileReprocessInternalFlag::class, $jobId);
        $this->assertSame(JobStatus::Done, $job->getStatus());

        // Total file count across all batches: 3 files (1 from Asset1, 2 from Asset2).
        $result = JobAssetFileReprocessInternalFlagResult::fromString($job->getResult());
        $this->assertSame(3, $result->getTotalCount());
    }

    public function testProcessRespectsOverrideInternalFlag(): void
    {
        // Activate the internal rule for the fixture licence.
        /** @var AssetLicence $licence */
        $licence = $this->entityManager->find(AssetLicence::class, AssetLicenceFixtures::LICENCE_ID);
        $licence->getInternalRule()->setActive(true);

        // IMAGE_ID_1: overrideInternal=true, internal=false — should be skipped by the evaluator.
        /** @var ImageFile $image1 */
        $image1 = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1);
        $image1->getFlags()->setOverrideInternal(true);
        $image1->getFlags()->setInternal(false);

        // IMAGE_ID_2: internal=false — should be set to true (changed).
        /** @var ImageFile $image2 */
        $image2 = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_2);
        $image2->getFlags()->setInternal(false);

        $this->entityManager->flush();

        $job = $this->entityManager->getRepository(JobAssetFileReprocessInternalFlag::class)->findAll()[0];
        $this->assertInstanceOf(JobAssetFileReprocessInternalFlag::class, $job);

        $this->processor->process($job);

        $this->assertSame(JobStatus::Done, $job->getStatus());

        $result = JobAssetFileReprocessInternalFlagResult::fromString($job->getResult());
        // Only IMAGE_ID_2 changed; IMAGE_ID_1 was skipped due to overrideInternal.
        $this->assertSame(1, $result->getChangedCount());

        // Re-fetch IMAGE_ID_1 and assert it is still false (unchanged).
        $this->entityManager->clear();

        /** @var ImageFile $image1Refetched */
        $image1Refetched = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1);
        $this->assertFalse($image1Refetched->getFlags()->isInternal());
    }

    public function testProcessFromFiltersAssetsByCreatedAt(): void
    {
        // Activate the internal rule for the fixture licence.
        /** @var AssetLicence $licence */
        $licence = $this->entityManager->find(AssetLicence::class, AssetLicenceFixtures::LICENCE_ID);
        $licence->getInternalRule()->setActive(true);

        // Set IMAGE_ID_1 internal flag to false so it would be changed if processed.
        /** @var ImageFile $image1 */
        $image1 = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1);
        $image1->getFlags()->setInternal(false);

        // Set IMAGE_ID_2 internal flag to false so it would be changed if processed.
        /** @var ImageFile $image2 */
        $image2 = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_2);
        $image2->getFlags()->setInternal(false);

        $this->entityManager->flush();

        // Set processFrom far in the future — no assets should match.
        $job = $this->entityManager->getRepository(JobAssetFileReprocessInternalFlag::class)->findAll()[0];
        $this->assertInstanceOf(JobAssetFileReprocessInternalFlag::class, $job);
        $job->setProcessFrom(new DateTimeImmutable('+10 years'));

        $this->processor->process($job);

        $this->assertSame(JobStatus::Done, $job->getStatus());

        $result = JobAssetFileReprocessInternalFlagResult::fromString($job->getResult());
        // No assets matched the processFrom filter.
        $this->assertSame(0, $result->getChangedCount());
        $this->assertSame(0, $result->getTotalCount());

        // Re-fetch and assert images are still false (not processed).
        $this->entityManager->clear();

        /** @var ImageFile $image1Refetched */
        $image1Refetched = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1);
        $this->assertFalse($image1Refetched->getFlags()->isInternal());

        /** @var ImageFile $image2Refetched */
        $image2Refetched = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_2);
        $this->assertFalse($image2Refetched->getFlags()->isInternal());
    }

    public function testProcessFromWithPastDateProcessesAllAssets(): void
    {
        // Activate the internal rule for the fixture licence.
        /** @var AssetLicence $licence */
        $licence = $this->entityManager->find(AssetLicence::class, AssetLicenceFixtures::LICENCE_ID);
        $licence->getInternalRule()->setActive(true);

        // Set IMAGE_ID_1 and IMAGE_ID_2 internal flag to false so they will be changed.
        /** @var ImageFile $image1 */
        $image1 = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1);
        $image1->getFlags()->setInternal(false);

        /** @var ImageFile $image2 */
        $image2 = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_2);
        $image2->getFlags()->setInternal(false);

        $this->entityManager->flush();

        // Set processFrom far in the past — all assets should match.
        $job = $this->entityManager->getRepository(JobAssetFileReprocessInternalFlag::class)->findAll()[0];
        $this->assertInstanceOf(JobAssetFileReprocessInternalFlag::class, $job);
        $job->setProcessFrom(new DateTimeImmutable('2000-01-01'));

        $this->processor->process($job);

        $this->assertSame(JobStatus::Done, $job->getStatus());

        $result = JobAssetFileReprocessInternalFlagResult::fromString($job->getResult());
        // IMAGE_ID_1 and IMAGE_ID_2 flipped from false to true.
        $this->assertSame(2, $result->getChangedCount());
        // All 3 files under LICENCE_ID were processed (IMAGE_ID_1, IMAGE_ID_2, IMAGE_ID_2_1).
        $this->assertSame(3, $result->getTotalCount());
    }
}
