<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Job;

use AnzuSystems\CommonBundle\Domain\Job\JobManager;
use AnzuSystems\CoreDamBundle\Entity\JobPodcastSynchronizer;

final readonly class JobPodcastSynchronizerFactory
{
    private const int PRIORITY_NORMAL = 1;

    public function __construct(
        private JobManager $jobManager
    ) {
    }

    public function createPodcastSynchronizerJob(string $podcastId, bool $fullSync): JobPodcastSynchronizer
    {
        $job = (new JobPodcastSynchronizer())
            ->setPodcastId($podcastId)
            ->setPriority(self::PRIORITY_NORMAL)
            ->setFullSync($fullSync);

        $this->jobManager->create($job);

        return $job;
    }
}
