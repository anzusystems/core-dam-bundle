<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Job\Processor;

use AnzuSystems\CommonBundle\Domain\Job\Processor\AbstractJobProcessor;
use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use AnzuSystems\CoreDamBundle\Domain\Podcast\PodcastImportIterator;
use AnzuSystems\CoreDamBundle\Domain\Podcast\RssImportManager;
use AnzuSystems\CoreDamBundle\Domain\PodcastEpisode\EpisodeRssImportManager;
use AnzuSystems\CoreDamBundle\Entity\JobPodcastSynchronizer;
use AnzuSystems\CoreDamBundle\Model\Dto\Podcast\PodcastImportIteratorDto;
use AnzuSystems\CoreDamBundle\Model\Enum\PodcastLastImportStatus;
use AnzuSystems\CoreDamBundle\Model\ValueObject\PodcastSynchronizerPointer;
use AnzuSystems\CoreDamBundle\Repository\PodcastRepository;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use DateTimeImmutable;
use DateTimeInterface;
use Generator;

final class JobPodcastSynchronizerProcessor extends AbstractJobProcessor
{
    private const int BULK_SIZE = 20;

    public function __construct(
        private readonly EpisodeRssImportManager $episodeRssImportManager,
        private readonly RssImportManager $rssImportManager,
        private readonly PodcastImportIterator $importIterator,
        private readonly PodcastRepository $podcastRepository,
        private int $bulkSize = self::BULK_SIZE,
        private ?DateTimeImmutable $minImportFrom = null
    ) {
    }

    public function setMinImportFrom(?DateTimeImmutable $minImportFrom): self
    {
        $this->minImportFrom = $minImportFrom;

        return $this;
    }

    public function setBulkSize(int $bulkSize): self
    {
        $this->bulkSize = $bulkSize;

        return $this;
    }

    public static function getSupportedJob(): string
    {
        return JobPodcastSynchronizer::class;
    }

    /**
     * @param JobPodcastSynchronizer $job
     * @throws SerializerException
     */
    public function process(JobInterface $job): bool
    {
        $this->start($job);
        $this->processPodcasts($job);

        return true;
    }

    /**
     * @throws SerializerException
     */
    private function processPodcasts(JobPodcastSynchronizer $job): void
    {
        if ($job->isFullSync()) {
            $this->importFull(
                job: $job,
                generator: $this->importIterator->iterate(
                    pointer: PodcastSynchronizerPointer::fromString($job->getLastBatchProcessedRecord()),
                    minImportFrom: $this->minImportFrom
                )
            );

            return;
        }

        if (false === empty($job->getPodcastId())) {
            $podcast = $this->podcastRepository->find($job->getPodcastId());

            if (null === $podcast) {
                $this->finishFail($job, 'Podcast to import not found');

                return;
            }

            // only when single podcast is synced, we can use minImportFrom from podcast because of performance optimization
            $this->importFull(
                job: $job,
                generator: $this->importIterator->iteratePodcast(
                    pointer: PodcastSynchronizerPointer::fromString($job->getLastBatchProcessedRecord()),
                    podcastToImport: $podcast,
                    minImportFrom: $this->minImportFrom ?? $podcast->getDates()->getImportFrom()
                ),
            );

            return;
        }

        $this->finishFail($job, 'No podcast ID provided or full sync is not enabled');
    }

    /**
     * @param Generator<int, PodcastImportIteratorDto> $generator
     *
     * @throws SerializerException
     */
    private function importFull(JobPodcastSynchronizer $job, Generator $generator): void
    {
        $lastImportedDto = null;
        $imported = 0;

        /** @var PodcastImportIteratorDto $importDto */
        foreach ($generator as $importDto) {
            if ($importDto->getPodcast()->getAttributes()->getLastImportStatus()->is(PodcastLastImportStatus::NotImported)) {
                $this->rssImportManager->syncPodcast(
                    podcast: $importDto->getPodcast(),
                    channel: $importDto->getChannel()
                );
            }

            $lastImportedDto = $importDto;
            $episodeImportDto = $this->episodeRssImportManager->importEpisode(
                $importDto->getPodcast(),
                $importDto->getItem()
            );

            if ($episodeImportDto->isNewlyImported()) {
                $imported++;
            }

            if ($this->bulkSize === $imported) {
                break;
            }
        }

        $this->finishProcessCycle($lastImportedDto, $imported, $job);
    }

    private function finishProcessCycle(?PodcastImportIteratorDto $dto, int $imported, JobPodcastSynchronizer $job): void
    {
        if (null === $dto || $imported < $this->bulkSize) {
            $imported = (($this->getManagedJob($job)->getBatchProcessedIterationCount()) * $this->bulkSize) + $imported;
            $this->getManagedJob($job)->setResult(sprintf('Podcast job finished. Imported %d episodes.', $imported));
            $this->finishSuccess($job);

            return;
        }

        $pointer = (new PodcastSynchronizerPointer(
            $dto->getPodcast()->getId(),
            $dto->getItem()->getPubDate()
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
