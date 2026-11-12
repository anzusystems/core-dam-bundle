<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Job;

use AnzuSystems\CommonBundle\Domain\Job\JobManager;
use AnzuSystems\CommonBundle\Model\Enum\JobStatus;
use AnzuSystems\CoreDamBundle\DataFixtures\AuthorFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\ImageFixtures;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorManager;
use AnzuSystems\CoreDamBundle\Domain\Job\Processor\JobAuthorCurrentOptimizeProcessor;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\JobAuthorCurrentOptimize;
use AnzuSystems\CoreDamBundle\Model\ValueObject\JobAuthorCurrentOptimizeResult;
use AnzuSystems\CoreDamBundle\Repository\AuthorRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use Doctrine\Common\Collections\ArrayCollection;

final class JobAuthorCurrentOptimizeProcessorTest extends CoreDamKernelTestCase
{
    private JobAuthorCurrentOptimizeProcessor $processor;
    private Author $target;
    private Author $source;
    private Author $child;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = $this->getService(JobAuthorCurrentOptimizeProcessor::class);
        $this->target = $this->createAuthor('Optimize Target');
        $this->source = $this->createAuthor('Optimize Source');
        $this->child = $this->createAuthor('Optimize Source - Target');
        $this->child->addCurrentAuthor($this->target)->addCurrentAuthor($this->source);
        $this->entityManager->flush();
    }

    public function testProcessReplacesChildAuthorsAndSkipsAssetsOfTheTargetAlone(): void
    {
        $this->setAssetAuthors(ImageFixtures::IMAGE_ID_1_2, [$this->child]);
        $this->setAssetAuthors(ImageFixtures::IMAGE_ID_2, [$this->target]);
        $this->setAssetAuthors(ImageFixtures::IMAGE_ID_3, [$this->target, $this->child]);

        $job = $this->runJob($this->target);

        $this->assertSame(JobStatus::Done, $job->getStatus());
        $result = JobAuthorCurrentOptimizeResult::fromString($job->getResult());
        $this->assertSame(2, $result->getOptimizedCount());
        $this->assertSame(2, $result->getTotalCount());
        $this->assertSame(['Optimize Source', 'Optimize Target'], $this->assetAuthors(ImageFixtures::IMAGE_ID_1_2));
        $this->assertSame(['Optimize Target'], $this->assetAuthors(ImageFixtures::IMAGE_ID_2));
        $this->assertSame(['Optimize Source', 'Optimize Target'], $this->assetAuthors(ImageFixtures::IMAGE_ID_3));
    }

    public function testProcessReplacesTheJobAuthorWhenItHasCurrentAuthors(): void
    {
        $this->setAssetAuthors(ImageFixtures::IMAGE_ID_1_2, [$this->child]);

        $job = $this->runJob($this->child);

        $this->assertSame(JobStatus::Done, $job->getStatus());
        $this->assertSame(['Optimize Source', 'Optimize Target'], $this->assetAuthors(ImageFixtures::IMAGE_ID_1_2));
    }

    public function testProcessFinishesAuthorWithoutChildAndCurrentAuthors(): void
    {
        $this->setAssetAuthors(ImageFixtures::IMAGE_ID_2, [$this->source]);

        $job = $this->runJob($this->createAuthor('Optimize Alone'));

        $this->assertSame(JobStatus::Done, $job->getStatus());
        $this->assertSame(0, JobAuthorCurrentOptimizeResult::fromString($job->getResult())->getTotalCount());
    }

    public function testProcessFinishesWithTheLastPartialBatch(): void
    {
        foreach ([ImageFixtures::IMAGE_ID_1_2, ImageFixtures::IMAGE_ID_2, ImageFixtures::IMAGE_ID_3] as $imageId) {
            $this->setAssetAuthors($imageId, [$this->child]);
        }
        $this->entityManager->clear();
        $job = (new JobAuthorCurrentOptimize())->setAuthorId((string) $this->target->getId());
        $this->getService(JobManager::class)->create($job);
        $jobId = $job->getId();
        $this->processor->setBulkSize(2);

        $this->processor->process($job);
        $job = $this->entityManager->find(JobAuthorCurrentOptimize::class, $jobId);
        $this->assertSame(JobStatus::AwaitingBatchProcess, $job->getStatus());

        $this->processor->process($job);
        $job = $this->entityManager->find(JobAuthorCurrentOptimize::class, $jobId);
        $this->assertSame(JobStatus::Done, $job->getStatus());
        $this->assertSame(1, $job->getBatchProcessedIterationCount());
        $result = JobAuthorCurrentOptimizeResult::fromString($job->getResult());
        $this->assertSame(3, $result->getOptimizedCount());
        $this->assertSame(3, $result->getTotalCount());
    }

    private function runJob(Author $author): JobAuthorCurrentOptimize
    {
        $this->entityManager->clear();
        $job = (new JobAuthorCurrentOptimize())->setAuthorId((string) $author->getId());
        $this->getService(JobManager::class)->create($job);
        $jobId = $job->getId();

        $this->processor->process($job);

        return $this->entityManager->find(JobAuthorCurrentOptimize::class, $jobId);
    }

    private function createAuthor(string $name): Author
    {
        /** @var Author $templateAuthor */
        $templateAuthor = $this->getService(AuthorRepository::class)->find(AuthorFixtures::AUTHOR_1);
        $author = (new Author())
            ->setName($name)
            ->setIdentifier($name)
            ->setExtSystem($templateAuthor->getExtSystem())
        ;
        $this->getService(AuthorManager::class)->create($author);

        return $author;
    }

    /**
     * @param list<Author> $authors
     */
    private function setAssetAuthors(string $imageId, array $authors): void
    {
        /** @var ImageFile $image */
        $image = $this->entityManager->find(ImageFile::class, $imageId);
        $image->getAsset()->setAuthors(new ArrayCollection($authors));
        $this->entityManager->flush();
    }

    /**
     * @return list<string>
     */
    private function assetAuthors(string $imageId): array
    {
        /** @var ImageFile $image */
        $image = $this->entityManager->find(ImageFile::class, $imageId);
        $names = $image->getAsset()->getAuthors()->map(static fn (Author $author): string => $author->getName())->getValues();
        sort($names);

        return $names;
    }
}
