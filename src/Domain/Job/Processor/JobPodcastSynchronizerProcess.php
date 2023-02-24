<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Job\Processor;

use AnzuSystems\CommonBundle\Domain\Job\Processor\AbstractJobProcessor;
use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use AnzuSystems\CoreDamBundle\Domain\Podcast\RssImportManager;
use AnzuSystems\CoreDamBundle\Entity\JobPodcastSynchronizer;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Model\Dto\RssFeed\Item;
use AnzuSystems\CoreDamBundle\Repository\PodcastRepository;
use Throwable;

final class JobPodcastSynchronizerProcess extends AbstractJobProcessor
{
    public function __construct(
        private readonly RssImportManager $rssImportManager,
        private readonly PodcastRepository $podcastRepository,
    ) {
    }

    public static function getSupportedJob(): string
    {
        return JobPodcastSynchronizer::class;
    }

    /**
     * @param JobPodcastSynchronizer $job
     */
    public function process(JobInterface $job): void
    {
        $podcast = $this->podcastRepository->find($job->getPodcastId());
        if (false === ($podcast instanceof Podcast)) {
            $this->finishFail($job, sprintf('Podcast with id (%s) not found', $job->getPodcastId()));

            return;
        }

        $this->start($job);

        try {
            $this->entityManager->beginTransaction();
            $this->finishProcessCycle(
                job: $job,
                item: $this->rssImportManager->syncBulkPodcast(
                    podcast: $podcast,
                    startFromGuid: $job->getLastBatchProcessedRecord() ?: null
                )
            );
            $this->entityManager->commit();
        } catch (Throwable $throwable) {
            $this->entityManager->rollback();
            $this->finishFail($job, substr($throwable->getMessage(), 0, 255));
        }
    }

    private function finishProcessCycle(JobPodcastSynchronizer $job, ?Item $item = null): void
    {
        if (null === $item) {
            $this->getManagedJob($job)->setResult('Podcast was fully synced');
            $this->finishSuccess($job);

            return;
        }

        if (empty($item->getGuid())) {
            $this->getManagedJob($job)->setResult('Not possible to chain Podcast import, because Episode GUID is empty');
            $this->finishFail($job, 'Empty GUID');
        }

        $job = $this->getManagedJob($job)->setResult(
            sprintf('Last synced episode GUID (%s)', $item->getGuid())
        );
        $this->toAwaitingBatchProcess($job, $item->getGuid());
    }
}
