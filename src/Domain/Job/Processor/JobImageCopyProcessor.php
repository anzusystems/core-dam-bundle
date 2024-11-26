<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Job\Processor;

use AnzuSystems\CommonBundle\Domain\Job\Processor\AbstractJobProcessor;
use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use AnzuSystems\CommonBundle\Traits\EntityManagerAwareTrait;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageCopyFacade;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopy;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopyItem;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageCopyDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCopyStatus;
use AnzuSystems\CoreDamBundle\Model\ValueObject\JobImageCopyResult;
use AnzuSystems\CoreDamBundle\Repository\JobImageCopyItemRepository;
use Doctrine\Common\Collections\Collection;
use Throwable;

final class JobImageCopyProcessor extends AbstractJobProcessor
{
    use EntityManagerAwareTrait;

    private const int ASSET_BULK_SIZE = 10;

    public function __construct(
        private readonly ImageCopyFacade $imageCopyFacade,
        private readonly JobImageCopyItemRepository $jobImageCopyItemRepository,
        private int $bulkSize = self::ASSET_BULK_SIZE,
    ) {
    }

    public static function getSupportedJob(): string
    {
        return JobImageCopy::class;
    }

    public function setBulkSize(int $bulkSize): void
    {
        $this->bulkSize = $bulkSize;
    }

    /**
     * @param JobImageCopy $job
     */
    public function process(JobInterface $job): void
    {
        $this->start($job);

        try {
            $res = $this->jobImageCopyItemRepository->findUnassignedByJob($job, (int) $job->getLastBatchProcessedRecord(), $this->bulkSize);
            $lastProcessedRecord = '';
            foreach ($res as $copyItem) {
                $this->processItem($copyItem);
                $lastProcessedRecord = (string) $copyItem->getId();
            }

            $this->finishCycle($job, $res, $lastProcessedRecord);
        } catch (Throwable $e) {
            $this->finishFail($job, $e->getMessage());
        }
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

    private function processItem(JobImageCopyItem $item): void
    {
        $copyDto = (new ImageCopyDto())
            ->setAsset($item->getSourceAsset())
            ->setTargetAssetLicence($item->getJob()->getLicence())
        ;
        $copyDtoRes = $this->imageCopyFacade->prepareCopy($copyDto);

        if ($copyDtoRes->getResult()->is(AssetFileCopyStatus::Copy) && $copyDtoRes->getTargetAsset()) {
            $this->imageCopyFacade->copyAssetFiles(
                asset: $copyDtoRes->getAsset(),
                copyAsset: $copyDtoRes->getTargetAsset(),
                copyTrackingFields: true
            );
        }

        $item->setStatus($copyDtoRes->getResult());
        $item->setTargetAsset($copyDtoRes->getTargetAsset());
    }
}
