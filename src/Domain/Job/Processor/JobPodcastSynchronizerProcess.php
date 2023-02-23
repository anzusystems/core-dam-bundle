<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Job\Processor;

use AnzuSystems\CommonBundle\Domain\Job\Processor\AbstractJobProcessor;
use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use AnzuSystems\CoreDamBundle\Domain\Podcast\RssImportManager;
use AnzuSystems\CoreDamBundle\Entity\JobPodcastSynchronizer;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
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

            $this->rssImportManager->syncPodcast(
                podcast: $podcast,
                fullImport: $job->isFullSync()
            );

            $this->getManagedJob($job)->setResult('Podcast synced');
            $this->finishSuccess($job);

            $this->entityManager->commit();
        } catch (Throwable $throwable) {
            $this->entityManager->rollback();
            $this->finishFail($job, substr($throwable->getMessage(), 0, 255));
        }
    }
}
