<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Job\Processor;

use AnzuSystems\CommonBundle\Domain\Job\Processor\AbstractJobProcessor;
use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\JobSynchronizeImageChanged;
use AnzuSystems\CoreDamBundle\Model\ValueObject\JobSynchronizeImageChangedResult;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use RuntimeException;
use Throwable;

final class JobSynchronizeImageChangedProcessor extends AbstractJobProcessor
{
    public function __construct(
        private readonly ImageFileRepository $imageFileRepository,
        private readonly AssetLicenceRepository $assetLicenceRepository,
        private readonly ExtSystemCallbackFacade $extSystemCallbackFacade,
    ) {
    }

    public static function getSupportedJob(): string
    {
        return JobSynchronizeImageChanged::class;
    }

    /**
     * @param JobSynchronizeImageChanged $job
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

    private function processLicence(JobSynchronizeImageChanged $job): void
    {
        $licence = $this->assetLicenceRepository->find($job->getTargetLicenceId());
        if (false === ($licence instanceof AssetLicence)) {
            $this->finishFail($job, new RuntimeException(
                sprintf('AssetLicence with ID %d not found', $job->getTargetLicenceId())
            ));

            return;
        }

        $bulkSize = $job->getBulkSize();
        $lastId = $job->getLastBatchProcessedRecord();
        $imageFiles = $this->imageFileRepository->findAllByLicence($licence, $bulkSize, $lastId, $job->getProcessFrom());

        $notifiedCount = 0;
        $totalCount = $imageFiles->count();

        if ($totalCount > 0) {
            $this->extSystemCallbackFacade->notifyImagesChanged($imageFiles);
            $notifiedCount = $totalCount;
        }

        /** @var string $lastProcessedId */
        $lastProcessedId = $lastId;
        foreach ($imageFiles as $imageFile) {
            $lastProcessedId = (string) $imageFile->getId();
        }

        $resultBefore = JobSynchronizeImageChangedResult::fromString($job->getResult());
        $resultNew = new JobSynchronizeImageChangedResult(
            $resultBefore->getNotifiedCount() + $notifiedCount,
            $resultBefore->getTotalCount() + $totalCount,
        );
        $this->getManagedJob($job)->setResult($resultNew->toString());

        $bulkSize === $imageFiles->count()
            ? $this->toAwaitingBatchProcess($job, $lastProcessedId)
            : $this->finishSuccess($job);
    }
}
