<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Job\Processor;

use AnzuSystems\CommonBundle\Domain\Job\Processor\AbstractJobProcessor;
use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use AnzuSystems\CoreDamBundle\Domain\Podcast\PodcastImportIterator;
use AnzuSystems\CoreDamBundle\Domain\Podcast\PodcastRssReader;
use AnzuSystems\CoreDamBundle\Domain\Podcast\RssImportManager;
use AnzuSystems\CoreDamBundle\Domain\PodcastEpisode\EpisodeRssImportManager;
use AnzuSystems\CoreDamBundle\Entity\JobPodcastSynchronizer;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\HttpClient\RssClient;
use AnzuSystems\CoreDamBundle\Model\Dto\Podcast\PodcastImportIteratorDto;
use AnzuSystems\CoreDamBundle\Model\Dto\RssFeed\Item;
use AnzuSystems\CoreDamBundle\Model\Enum\PodcastLastImportStatus;
use AnzuSystems\CoreDamBundle\Model\ValueObject\PodcastSynchronizerPointer;
use AnzuSystems\CoreDamBundle\Repository\PodcastRepository;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use DateTimeInterface;
use Throwable;

final class JobPodcastSynchronizerProcessor extends AbstractJobProcessor
{
    private const BULK_SIZE = 2;

    public function __construct(
        private readonly RssImportManager $rssImportManager,
        private readonly EpisodeRssImportManager $episodeRssImportManager,
        private readonly PodcastRepository $podcastRepository,
        private readonly PodcastRssReader $reader,
        private readonly RssClient $client,
        private readonly PodcastImportIterator $importIterator,
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
        // todo job contains PODCAST ID

        $lastImportedDto = null;
        $imported = 0;

        foreach ($this->importIterator->iterate(PodcastSynchronizerPointer::fromString($job->getLastBatchProcessedRecord())) as $importDto) {
            // todo import PODCAST ! and sets last import dateTime
            $lastImportedDto = $importDto;
            $episodeImportDto = $this->episodeRssImportManager->importEpisode(
                $importDto->getPodcast(),
                $importDto->getItem()
            );

            if ($episodeImportDto->isNewlyImported()) {
                $imported++;
            }

            if (self::BULK_SIZE === $imported) {
                break;
            }

        }

        $this->finishProcessCycle($lastImportedDto, $imported, $job);
    }


    private function finishProcessCycle(?PodcastImportIteratorDto $dto, int $imported, JobPodcastSynchronizer $job): void
    {
        if (null === $dto || $imported < self::BULK_SIZE) {
            $this->getManagedJob($job)->setResult('Podcast job finished');
            $this->finishSuccess($job);

            return;
        }

        $pointer = (new PodcastSynchronizerPointer(
            $dto->getPodcast()->getId(),
            $dto->getItem()?->getPubDate()
        ));

        $job = $this->getManagedJob($job)->setResult(
            sprintf(
                'Last synced Podcast (%s) at date (%s)',
                $pointer->getPodcastId(),
                $pointer->getPubDate()?->format(DateTimeInterface::ATOM)
            )
        );

        $this->toAwaitingBatchProcess(
            job: $job,
            lastProcessedRecord: $pointer->toString()
        );
    }
}
