<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Job\Processor;

use AnzuSystems\CommonBundle\Domain\Job\Processor\AbstractJobProcessor;
use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\JobAuthorNameReindex;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\ValueObject\JobAuthorNameReindexResult;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Repository\AuthorRepository;
use AnzuSystems\CoreDamBundle\Traits\IndexManagerAwareTrait;
use RuntimeException;
use Throwable;

final class JobAuthorNameReindexProcessor extends AbstractJobProcessor
{
    use IndexManagerAwareTrait;

    private const int ASSET_BULK_SIZE = 500;

    public function __construct(
        private readonly AssetRepository $assetRepository,
        private readonly AuthorRepository $authorRepository,
        private readonly DamLogger $damLogger,
        private int $bulkSize = self::ASSET_BULK_SIZE,
    ) {
    }

    public static function getSupportedJob(): string
    {
        return JobAuthorNameReindex::class;
    }

    public function setBulkSize(int $bulkSize): void
    {
        $this->bulkSize = $bulkSize;
    }

    /**
     * @param JobAuthorNameReindex $job
     */
    public function process(JobInterface $job): bool
    {
        $this->start($job);

        try {
            $this->processAuthor($job);
            $this->entityManager->clear();
        } catch (Throwable $e) {
            $this->damLogger->error(
                DamLogger::NAMESPACE_JOB,
                sprintf('JobAuthorNameReindex (%s) failed', (string) $job->getId()),
                exception: $e,
            );
            $this->finishFail($job, $e);
        }

        return true;
    }

    private function processAuthor(JobAuthorNameReindex $job): void
    {
        /** @var Author|null $author */
        $author = $this->authorRepository->find($job->getAuthorId());
        if (null === $author) {
            $this->finishFail($job, new RuntimeException(
                sprintf('Author with ID %s not found', $job->getAuthorId())
            ));

            return;
        }

        $lastId = $job->getLastBatchProcessedRecord();
        $assets = $this->assetRepository->findByAuthorIds([(string) $author->getId()], $lastId, $this->bulkSize);

        $lastProcessedId = $lastId;
        foreach ($assets as $asset) {
            $lastProcessedId = (string) $asset->getId();
        }

        if (false === $assets->isEmpty()) {
            $this->indexManager->indexBulk($assets->toArray());
        }

        $count = $assets->count();
        $resultBefore = JobAuthorNameReindexResult::fromString($job->getResult());
        $resultNew = new JobAuthorNameReindexResult(
            $resultBefore->getReindexedCount() + $count,
            $resultBefore->getTotalCount() + $count,
        );
        $this->getManagedJob($job)->setResult($resultNew->toString());

        $this->bulkSize === $count
            ? $this->toAwaitingBatchProcess($job, $lastProcessedId)
            : $this->finishSuccess($job);
    }
}
