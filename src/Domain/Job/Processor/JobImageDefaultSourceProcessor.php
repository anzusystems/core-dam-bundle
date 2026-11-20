<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Job\Processor;

use AnzuSystems\CommonBundle\Domain\Job\Processor\AbstractJobProcessor;
use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use AnzuSystems\CommonBundle\Traits\EntityManagerAwareTrait;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorProvider;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\JobImageDefaultSource;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Repository\DBALRepository\AssetSourceDBALRepository;
use AnzuSystems\CoreDamBundle\Repository\ExtSystemRepository;
use AnzuSystems\CoreDamBundle\Traits\IndexManagerAwareTrait;
use RuntimeException;
use Throwable;

/**
 * Links the default source straight in SQL, a batch of images at a time: millions of images need no logic of the
 * entities, and the job queue is shared with other jobs.
 */
final class JobImageDefaultSourceProcessor extends AbstractJobProcessor
{
    use EntityManagerAwareTrait;
    use IndexManagerAwareTrait;

    private const int ASSET_BULK_SIZE = 500;

    public function __construct(
        private readonly ExtSystemRepository $extSystemRepository,
        private readonly AssetRepository $assetRepository,
        private readonly AssetSourceDBALRepository $assetSourceDBALRepository,
        private readonly AuthorProvider $authorProvider,
        private readonly DamLogger $damLogger,
        private int $bulkSize = self::ASSET_BULK_SIZE,
    ) {
    }

    public static function getSupportedJob(): string
    {
        return JobImageDefaultSource::class;
    }

    public function setBulkSize(int $bulkSize): void
    {
        $this->bulkSize = $bulkSize;
    }

    /**
     * @param JobImageDefaultSource $job
     */
    public function process(JobInterface $job): bool
    {
        $this->start($job);

        try {
            $this->processBatch($job);
            $this->entityManager->clear();
        } catch (Throwable $e) {
            $this->damLogger->error(
                DamLogger::NAMESPACE_JOB,
                sprintf('JobImageDefaultSource (%s) failed', (string) $job->getId()),
                exception: $e,
            );
            $this->finishFail($job, $e);
        }

        return true;
    }

    private function processBatch(JobImageDefaultSource $job): void
    {
        $extSystem = $this->extSystemRepository->find($job->getExtSystemId());
        $source = $extSystem instanceof ExtSystem ? $this->authorProvider->getDefaultSource($extSystem, AssetType::Image) : null;
        if (null === $source) {
            $this->finishFail($job, new RuntimeException('default_source_not_set'));

            return;
        }

        $assetIds = $this->assetSourceDBALRepository->findImageIdsWithFileWithoutSource(
            $job->getExtSystemId(),
            $job->getLastBatchProcessedRecord(),
            $this->bulkSize,
        );
        if (false === empty($assetIds)) {
            $this->assetSourceDBALRepository->linkSource($assetIds, (string) $source->getId());
            $this->indexManager->indexBulk($this->assetRepository->findByIds($assetIds)->getValues());
        }
        $this->getManagedJob($job)->setResult((string) ((int) $job->getResult() + count($assetIds)));

        count($assetIds) === $this->bulkSize
            ? $this->toAwaitingBatchProcess($job, $assetIds[array_key_last($assetIds)])
            : $this->finishSuccess($job);
    }
}
