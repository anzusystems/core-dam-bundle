<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Job\Processor;

use AnzuSystems\CommonBundle\Domain\Job\Processor\AbstractJobProcessor;
use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use AnzuSystems\CommonBundle\Traits\EntityManagerAwareTrait;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageCopyFacade;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\JobAuthorCurrentOptimize;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopy;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopyItem;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageCopyDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCopyStatus;
use AnzuSystems\CoreDamBundle\Model\ValueObject\JobImageCopyResult;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Repository\AuthorRepository;
use AnzuSystems\CoreDamBundle\Repository\JobImageCopyItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Throwable;

final class JobAuthorCurrentOptimizeProcessor extends AbstractJobProcessor
{
    use EntityManagerAwareTrait;

    private const int ASSET_BULK_SIZE = 10;

    public function __construct(
        private readonly ImageCopyFacade $imageCopyFacade,
        private readonly JobImageCopyItemRepository $jobImageCopyItemRepository,
        private readonly AssetRepository $assetRepository,
        private readonly AuthorRepository $authorRepository,
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
    public function process(JobInterface $job): void
    {
//        $this->start($job);

        try {
            /** @var Collection<int, Author> $currentAuthorColl */
            $authors = new ArrayCollection(array_values(
                array_filter(
                    array_map(fn(string $id): ?Author => $this->authorRepository->find($id), $job->getAuthorIds()),
                )
            ));

            foreach ($authors as $author) {
                $this->processAuthor($author);
            }
        } catch (Throwable $e) {
            $this->finishFail($job, $e->getMessage());
        }
    }

    private function processAuthor(Author $author): void
    {
        $assets = $this->assetRepository->findByAuthor($author);
        dd($assets->count());
    }

    /**
     * @param Collection<int, JobImageCopyItem> $items
     */
    private function finishCycle(JobImageCopy $job, Collection $items, string $lastProcessedRecord = ''): void
    {
        $previousResult = JobImageCopyResult::fromString($job->getResult());
        $job->setBatchProcessedIterationCount($items->count() + $job->getBatchProcessedIterationCount());

        $existsCount = $previousResult->getExistsCount();
        $copyCount = $previousResult->getCopyCount();
        $notAllowedCount = $previousResult->getNotAllowedCount();

        foreach ($items as $item) {
            match ($item->getStatus()) {
                AssetFileCopyStatus::Copy => $copyCount++,
                AssetFileCopyStatus::Exists => $existsCount++,
                AssetFileCopyStatus::Unassigned => $notAllowedCount++,
                AssetFileCopyStatus::NotAllowed => null
            };
        }

        $this->getManagedJob($job)->setResult((new JobImageCopyResult(
            copyCount: $copyCount,
            existsCount: $existsCount,
            notAllowedCount: $notAllowedCount
        ))->toString());

        $items->count() === $this->bulkSize
            ? $this->toAwaitingBatchProcess($job, $lastProcessedRecord)
            : $this->finishSuccess($job)
        ;
    }
}
