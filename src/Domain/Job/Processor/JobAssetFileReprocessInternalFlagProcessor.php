<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Job\Processor;

use AnzuSystems\CommonBundle\Domain\Job\Processor\AbstractJobProcessor;
use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileInternalRuleEvaluator;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\JobAssetFileReprocessInternalFlag;
use AnzuSystems\CoreDamBundle\Model\ValueObject\JobAssetFileReprocessInternalFlagResult;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use RuntimeException;
use Throwable;

final class JobAssetFileReprocessInternalFlagProcessor extends AbstractJobProcessor
{
    public function __construct(
        private readonly AssetRepository $assetRepository,
        private readonly AssetLicenceRepository $assetLicenceRepository,
        private readonly AssetFileInternalRuleEvaluator $evaluator,
    ) {
    }

    public static function getSupportedJob(): string
    {
        return JobAssetFileReprocessInternalFlag::class;
    }

    /**
     * @param JobAssetFileReprocessInternalFlag $job
     */
    public function process(JobInterface $job): bool
    {
        $this->start($job);

        try {
            $this->processLicence($job);
            $this->entityManager->clear();
        } catch (Throwable $e) {
            $this->finishFail($job, $e);
        }

        return true;
    }

    private function processLicence(JobAssetFileReprocessInternalFlag $job): void
    {
        $licence = $this->assetLicenceRepository->find($job->getTargetLicenceId());
        if (false === ($licence instanceof AssetLicence)) {
            $this->finishFail($job, new RuntimeException(
                sprintf('AssetLicence with ID %d not found', $job->getTargetLicenceId())
            ));

            return;
        }

        if (false === $licence->getInternalRule()->isActive()) {
            $this->finishFail($job, new RuntimeException(
                sprintf('Internal rule is not active for licence %d', $job->getTargetLicenceId())
            ));

            return;
        }

        $bulkSize = $job->getBulkSize();
        $lastId = $job->getLastBatchProcessedRecord();
        $assets = $this->assetRepository->findAllByLicence($licence, $bulkSize, $lastId, $job->getProcessFrom());

        $changedCount = 0;
        $totalFileCount = 0;
        foreach ($assets as $asset) {
            foreach ($asset->getSlots() as $slot) {
                $assetFile = $slot->getAssetFile();
                $oldInternal = $assetFile->getFlags()->isInternal();
                $this->evaluator->evaluateAndApply($asset, $assetFile);
                if ($oldInternal !== $assetFile->getFlags()->isInternal()) {
                    $changedCount++;
                }
                $totalFileCount++;
            }
            $lastId = (string) $asset->getId();
        }

        $resultBefore = JobAssetFileReprocessInternalFlagResult::fromString($job->getResult());
        $resultNew = new JobAssetFileReprocessInternalFlagResult(
            $resultBefore->getChangedCount() + $changedCount,
            $resultBefore->getTotalCount() + $totalFileCount,
        );
        $this->getManagedJob($job)->setResult($resultNew->toString());

        $bulkSize === $assets->count()
            ? $this->toAwaitingBatchProcess($job, $lastId)
            : $this->finishSuccess($job);
    }
}
