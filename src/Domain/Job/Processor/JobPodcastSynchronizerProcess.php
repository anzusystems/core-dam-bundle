<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Job\Processor;

use AnzuSystems\CommonBundle\Domain\Job\Processor\AbstractJobProcessor;
use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use AnzuSystems\CoreDamBundle\Domain\Podcast\PodcastRssReader;
use AnzuSystems\CoreDamBundle\Domain\Podcast\RssImportManager;
use AnzuSystems\CoreDamBundle\Domain\PodcastEpisode\EpisodeRssImportManager;
use AnzuSystems\CoreDamBundle\Entity\JobPodcastSynchronizer;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\HttpClient\RssClient;
use AnzuSystems\CoreDamBundle\Model\Dto\RssFeed\Item;
use AnzuSystems\CoreDamBundle\Model\Enum\PodcastLastImportStatus;
use AnzuSystems\CoreDamBundle\Repository\PodcastRepository;
use Throwable;

final class JobPodcastSynchronizerProcess extends AbstractJobProcessor
{
    private const BULK_SIZE = 20;

    public function __construct(
        private readonly RssImportManager $rssImportManager,
        private readonly EpisodeRssImportManager $episodeRssImportManager,
        private readonly PodcastRepository $podcastRepository,
        private readonly PodcastRssReader $reader,
        private readonly RssClient $client,
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
            $this->reader->initReader($this->client->readPodcastRss($podcast));

            if ($podcast->getAttributes()->getLastImportStatus()->is(PodcastLastImportStatus::NotImported)) {
                $this->rssImportManager->syncPodcast($podcast, $this->reader->readChannel());
            }

            $imported = 0;
            $lastPodcastItem = null;

            foreach ($this->reader->readItems($job->getLastBatchProcessedRecord()) as $podcastItem) {
                $lastPodcastItem = $podcastItem;
                $episodeImportDto = $this->episodeRssImportManager->importEpisode($podcast, $podcastItem);

                if ($episodeImportDto->isNewlyImported()) {
                    $imported++;
                }

                if (self::BULK_SIZE === $imported) {
                    break;
                }
            }

            $this->finishProcessCycle(
                job: $job,
                item: $lastPodcastItem
            );
        } catch (Throwable $throwable) {
            $this->finishFail($job, substr($throwable->getMessage(), 0, 255));
        }
    }

    private function finishProcessCycle(JobPodcastSynchronizer $job, ?Item $item = null): void
    {
        if (null === $item) {
            $this->getManagedJob($job)->setResult(
                sprintf('Podcast (%s) was fully synced', 'TODO PODCAST TITLE')
            );
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
