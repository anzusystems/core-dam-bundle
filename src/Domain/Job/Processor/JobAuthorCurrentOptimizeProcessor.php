<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Job\Processor;

use AnzuSystems\CommonBundle\Domain\Job\Processor\AbstractJobProcessor;
use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use AnzuSystems\CommonBundle\Traits\EntityManagerAwareTrait;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorProvider;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageCopyFacade;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\JobAuthorCurrentOptimize;
use AnzuSystems\CoreDamBundle\Model\ValueObject\JobAuthorCurrentOptimizeResult;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Repository\AuthorRepository;
use AnzuSystems\CoreDamBundle\Repository\JobImageCopyItemRepository;
use Doctrine\Common\Collections\Collection;
use Throwable;

final class JobAuthorCurrentOptimizeProcessor extends AbstractJobProcessor
{
    use EntityManagerAwareTrait;

    private const int ASSET_BULK_SIZE = 500;

    public function __construct(
        private readonly AssetRepository $assetRepository,
        private readonly AuthorRepository $authorRepository,
        private readonly AuthorProvider $authorProvider,
        private int $bulkSize = self::ASSET_BULK_SIZE,
    ) {
    }

    public static function getSupportedJob(): string
    {
        return JobAuthorCurrentOptimize::class;
    }

    public function setBulkSize(int $bulkSize): void
    {
        $this->bulkSize = $bulkSize;
    }

    /**
     * @param JobAuthorCurrentOptimize $job
     */
    public function process(JobInterface $job): bool
    {
        $this->start($job);

        try {
            $job->isProcessAll()
                ? $this->processAll($job)
                : $this->processAuthor($job)
            ;
            $this->entityManager->clear();
        } catch (Throwable $e) {
            $this->finishFail($job, $e->getMessage());
        }

        return true;
    }

    private function processAll(JobAuthorCurrentOptimize $job): void
    {
        $lastId = $job->getLastBatchProcessedRecord();
        $assets = $this->assetRepository->getAll(idFrom: $lastId, limit: $this->bulkSize);

        $this->processAssetsCollection($job, $assets, $lastId);
    }

    private function processAuthor(JobAuthorCurrentOptimize $job): void
    {
        /** @var Author|null $author */
        $author = $this->authorRepository->find($job->getAuthorId());
        if (null === $author) {
            $this->finishFail($job, 'Author not found');

            return;
        }

        $lastId = $job->getLastBatchProcessedRecord();
        $assets = $this->assetRepository->findByAuthor($author, $lastId, $this->bulkSize);

        $this->processAssetsCollection($job, $assets, $lastId);
    }

    /**
     * @param Collection<int, Asset> $assets
     */
    private function processAssetsCollection(JobAuthorCurrentOptimize $job, Collection $assets, string $lastId = ''): void
    {
        $changedAuthorsCount = 0;
        foreach ($assets as $asset) {
            if ($this->authorProvider->provideCurrentAuthorToColl($asset)) {
                $changedAuthorsCount++;
            }
            $lastId = (string) $asset->getId();
        }

        $count = $assets->count();
        $resultBefore = JobAuthorCurrentOptimizeResult::fromString($job->getResult());
        $resultNew = new JobAuthorCurrentOptimizeResult(
            $resultBefore->getOptimizedCount() + $changedAuthorsCount,
            $resultBefore->getTotalCount() + $assets->count(),
        );
        $this->getManagedJob($job)->setResult($resultNew->toString());

        $count === $this->bulkSize
            ? $this->toAwaitingBatchProcess($job, $lastId)
            : $this->finishSuccess($job);
    }
}
