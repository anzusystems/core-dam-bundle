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
use PHPUnit\Framework\Attributes\DataProvider;

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

    public function testProcessReplacesChildAuthorInSources(): void
    {
        $agency = $this->createAuthor('Optimize Agency', source: true);
        $this->setAssetSources(ImageFixtures::IMAGE_ID_1_2, [$this->createChildAuthor('Optimize Agency.', $agency, source: true)]);

        $job = $this->runJob($agency);

        $this->assertSame(JobStatus::Done, $job->getStatus());
        $this->assertSame(1, JobAuthorCurrentOptimizeResult::fromString($job->getResult())->getOptimizedCount());
        $this->assertSame(['Optimize Agency'], $this->assetSources(ImageFixtures::IMAGE_ID_1_2));
    }

    public function testProcessReplacingSourceWithEnforcedSourceSwitchesSingleUse(): void
    {
        $agency = $this->createAuthor('Optimize Single Use Agency', source: true);
        $agency->getFlags()->setSingleUseEnforced(true);
        $this->setAssetSources(ImageFixtures::IMAGE_ID_1_2, [$this->createChildAuthor('Optimize Single Use Agency.', $agency, source: true)]);
        $this->assertFalse($this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1_2)->getFlags()->isSingleUse());

        $this->runJob($agency);

        $this->assertTrue($this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1_2)->getFlags()->isSingleUse());
    }

    #[DataProvider('lastPartialBatchDataProvider')]
    public function testProcessFinishesWithTheLastPartialBatch(bool $twoChildAuthorsPerAsset): void
    {
        $children = $twoChildAuthorsPerAsset
            ? [$this->child, $this->createChildAuthor('Optimize Target - Second', $this->target)]
            : [$this->child];
        foreach ([ImageFixtures::IMAGE_ID_1_2, ImageFixtures::IMAGE_ID_2, ImageFixtures::IMAGE_ID_3] as $imageId) {
            $this->setAssetAuthors($imageId, $children);
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

    public static function lastPartialBatchDataProvider(): array
    {
        return [
            'One child author per asset' => [
                'twoChildAuthorsPerAsset' => false,
            ],
            'Two child authors per asset' => [
                'twoChildAuthorsPerAsset' => true,
            ],
        ];
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

    private function createAuthor(string $name, bool $source = false): Author
    {
        /** @var Author $templateAuthor */
        $templateAuthor = $this->getService(AuthorRepository::class)->find(AuthorFixtures::AUTHOR_1);
        $author = (new Author())
            ->setName($name)
            ->setIdentifier($name)
            ->setExtSystem($templateAuthor->getExtSystem())
        ;
        $author->getFlags()->setSource($source);
        $this->getService(AuthorManager::class)->create($author);

        return $author;
    }

    private function createChildAuthor(string $name, Author $currentAuthor, bool $source = false): Author
    {
        $author = $this->createAuthor($name, $source);
        $author->addCurrentAuthor($currentAuthor);
        $this->entityManager->flush();

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
     * @param list<Author> $sources
     */
    private function setAssetSources(string $imageId, array $sources): void
    {
        /** @var ImageFile $image */
        $image = $this->entityManager->find(ImageFile::class, $imageId);
        $image->getAsset()->setSources(new ArrayCollection($sources));
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

    /**
     * @return list<string>
     */
    private function assetSources(string $imageId): array
    {
        /** @var ImageFile $image */
        $image = $this->entityManager->find(ImageFile::class, $imageId);
        $names = $image->getAsset()->getSources()->map(static fn (Author $author): string => $author->getName())->getValues();
        sort($names);

        return $names;
    }
}
