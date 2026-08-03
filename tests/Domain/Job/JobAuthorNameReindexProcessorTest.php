<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Job;

use AnzuSystems\CommonBundle\Domain\Job\JobManager;
use AnzuSystems\CommonBundle\Model\Enum\JobStatus;
use AnzuSystems\CoreDamBundle\DataFixtures\AuthorFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\ImageFixtures;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorManager;
use AnzuSystems\CoreDamBundle\Domain\Job\Processor\JobAuthorNameReindexProcessor;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\JobAuthorNameReindex;
use AnzuSystems\CoreDamBundle\Model\ValueObject\JobAuthorNameReindexResult;
use AnzuSystems\CoreDamBundle\Repository\AuthorRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use Doctrine\Common\Collections\ArrayCollection;

final class JobAuthorNameReindexProcessorTest extends CoreDamKernelTestCase
{
    private JobAuthorNameReindexProcessor $processor;
    private Author $batchAuthor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = $this->getService(JobAuthorNameReindexProcessor::class);
        $this->batchAuthor = $this->createBatchAuthorWithAssets();
    }

    public function testProcessFailsWhenAuthorNotFound(): void
    {
        $job = (new JobAuthorNameReindex())->setAuthorId('00000000-0000-0000-0000-000000000000');
        $this->getService(JobManager::class)->create($job);

        $this->processor->process($job);

        $this->assertSame(JobStatus::Error, $job->getStatus());
    }

    public function testProcessReindexesAllAuthorAssetsInOneBatch(): void
    {
        $job = (new JobAuthorNameReindex())->setAuthorId((string) $this->batchAuthor->getId());
        $this->getService(JobManager::class)->create($job);

        $this->processor->process($job);

        $this->assertSame(JobStatus::Done, $job->getStatus());

        $result = JobAuthorNameReindexResult::fromString($job->getResult());
        $this->assertSame(3, $result->getReindexedCount());
        $this->assertSame(3, $result->getTotalCount());
    }

    public function testProcessResumesAcrossMultipleBatches(): void
    {
        $job = (new JobAuthorNameReindex())->setAuthorId((string) $this->batchAuthor->getId());
        $this->getService(JobManager::class)->create($job);
        $jobId = $job->getId();

        $this->processor->setBulkSize(1);

        foreach ([1, 2, 3] as $expectedIterationCount) {
            $this->processor->process($job);
            $job = $this->entityManager->find(JobAuthorNameReindex::class, $jobId);
            $this->assertSame(JobStatus::AwaitingBatchProcess, $job->getStatus());
            $this->assertSame($expectedIterationCount, $job->getBatchProcessedIterationCount());
        }

        $this->processor->process($job);
        $job = $this->entityManager->find(JobAuthorNameReindex::class, $jobId);
        $this->assertSame(JobStatus::Done, $job->getStatus());

        $result = JobAuthorNameReindexResult::fromString($job->getResult());
        $this->assertSame(3, $result->getReindexedCount());
        $this->assertSame(3, $result->getTotalCount());
    }

    private function createBatchAuthorWithAssets(): Author
    {
        /** @var Author $templateAuthor */
        $templateAuthor = $this->getService(AuthorRepository::class)->find(AuthorFixtures::AUTHOR_1);

        $author = (new Author())
            ->setName('Batch Reindex Author')
            ->setIdentifier('batch-reindex-author')
            ->setExtSystem($templateAuthor->getExtSystem())
        ;
        $this->getService(AuthorManager::class)->create($author);

        foreach ([ImageFixtures::IMAGE_ID_1_2, ImageFixtures::IMAGE_ID_2, ImageFixtures::IMAGE_ID_3] as $imageId) {
            /** @var ImageFile $image */
            $image = $this->entityManager->find(ImageFile::class, $imageId);
            $image->getAsset()->setAuthors(new ArrayCollection([$author]));
        }
        $this->entityManager->flush();

        return $author;
    }
}
