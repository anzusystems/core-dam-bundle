<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Job;

use AnzuSystems\CommonBundle\Domain\Job\JobManager;
use AnzuSystems\CommonBundle\Model\Enum\JobStatus;
use AnzuSystems\CoreDamBundle\DataFixtures\AuthorFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\ImageFixtures;
use AnzuSystems\CoreDamBundle\Domain\Job\Processor\JobImageDefaultSourceProcessor;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\JobImageDefaultSource;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetStatus;
use AnzuSystems\CoreDamBundle\Repository\DBALRepository\AssetSourceDBALRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Domain\Author\AuthorTreeTrait;

final class JobImageDefaultSourceProcessorTest extends CoreDamKernelTestCase
{
    use AuthorTreeTrait;

    private JobImageDefaultSourceProcessor $processor;
    private AssetSourceDBALRepository $assetSourceDBALRepository;
    private int $batches = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = $this->getService(JobImageDefaultSourceProcessor::class);
        $this->processor->setBulkSize(2);
        $this->assetSourceDBALRepository = $this->getService(AssetSourceDBALRepository::class);
    }

    public function testFillsImagesWithFileWithoutSourceInBatches(): void
    {
        $this->markDefaultSource();
        $tasr = $this->createSource('TASR');
        $this->findImageAsset(ImageFixtures::IMAGE_ID_2)->addSource($tasr);
        $this->findImageAsset(ImageFixtures::IMAGE_ID_3)->getAttributes()->setStatus(AssetStatus::Draft);
        $this->entityManager->flush();
        $withFile = $this->countWithoutSource(AssetStatus::WithFile);
        $drafts = $this->countWithoutSource(AssetStatus::Draft);
        self::assertGreaterThan(2, $withFile);

        $job = $this->runJob();

        self::assertSame(JobStatus::Done, $job->getStatus());
        self::assertSame((string) $withFile, $job->getResult());
        self::assertSame((int) ceil(($withFile + 1) / 2), $this->batches);
        self::assertSame(0, $this->countWithoutSource(AssetStatus::WithFile));
        self::assertSame($drafts, $this->countWithoutSource(AssetStatus::Draft));
        self::assertSame([AuthorFixtures::AUTHOR_5], $this->sourceIds(ImageFixtures::IMAGE_ID_1_1));
        self::assertSame([(string) $tasr->getId()], $this->sourceIds(ImageFixtures::IMAGE_ID_2));
        self::assertSame([], $this->sourceIds(ImageFixtures::IMAGE_ID_3));

        $secondJob = $this->runJob();

        self::assertSame(JobStatus::Done, $secondJob->getStatus());
        self::assertSame('0', $secondJob->getResult());
    }

    public function testFailsWhenDefaultIsNotSource(): void
    {
        $withFile = $this->countWithoutSource(AssetStatus::WithFile);

        $job = $this->runJob();

        self::assertSame(JobStatus::Error, $job->getStatus());
        self::assertSame($withFile, $this->countWithoutSource(AssetStatus::WithFile));
    }

    private function runJob(): JobImageDefaultSource
    {
        $this->entityManager->clear();
        $job = (new JobImageDefaultSource())->setExtSystemId(ExtSystemFixtures::ID_CMS);
        $this->getService(JobManager::class)->create($job);
        $jobId = $job->getId();
        $this->batches = 0;
        do {
            $this->processor->process($job);
            $this->batches++;
            $job = $this->entityManager->find(JobImageDefaultSource::class, $jobId);
            self::assertInstanceOf(JobImageDefaultSource::class, $job);
        } while ($job->getStatus()->is(JobStatus::AwaitingBatchProcess));

        return $job;
    }

    private function countWithoutSource(AssetStatus $status): int
    {
        return $this->assetSourceDBALRepository->countImagesWithoutSource(ExtSystemFixtures::ID_CMS)[$status->value] ?? 0;
    }

    private function findImageAsset(string $imageId): Asset
    {
        $image = $this->entityManager->find(ImageFile::class, $imageId);
        self::assertInstanceOf(ImageFile::class, $image);

        return $image->getAsset();
    }

    /**
     * @return list<string>
     */
    private function sourceIds(string $imageId): array
    {
        $this->entityManager->clear();

        return $this->findImageAsset($imageId)->getSources()
            ->map(static fn (Author $source): string => (string) $source->getId())
            ->getValues();
    }
}
