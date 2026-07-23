<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Job\Processor;

use AnzuSystems\CommonBundle\Domain\Job\Processor\AbstractJobProcessor;
use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Podcast\PodcastImportIterator;
use AnzuSystems\CoreDamBundle\Domain\Podcast\RssImportManager;
use AnzuSystems\CoreDamBundle\Domain\PodcastEpisode\EpisodeRssImportManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\JobPodcastSynchronizer;
use AnzuSystems\CoreDamBundle\Event\Dispatcher\AssetChangedEventDispatcher;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Dto\Podcast\PodcastImportIteratorDto;
use AnzuSystems\CoreDamBundle\Model\Enum\PodcastLastImportStatus;
use AnzuSystems\CoreDamBundle\Model\ValueObject\PodcastSynchronizerPointer;
use AnzuSystems\CoreDamBundle\Repository\PodcastRepository;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Generator;
use Throwable;

final class JobPodcastSynchronizerProcessor extends AbstractJobProcessor
{
    private const int BULK_SIZE = 20;

    public function __construct(
        private readonly EpisodeRssImportManager $episodeRssImportManager,
        private readonly RssImportManager $rssImportManager,
        private readonly PodcastImportIterator $importIterator,
        private readonly PodcastRepository $podcastRepository,
        private readonly AssetChangedEventDispatcher $assetMetadataBulkEventDispatcher,
        private readonly DamLogger $damLogger,
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
        $failed = 0;

        /** @var array<int, Asset> $newlyImportedAssets */
        $newlyImportedAssets = [];

        /** @var PodcastImportIteratorDto $importDto */
        foreach ($generator as $importDto) {
            $lastImportedDto = $importDto;

            try {
                if ($importDto->getPodcast()->getAttributes()->getLastImportStatus()->is(PodcastLastImportStatus::NotImported)) {
                    $this->rssImportManager->syncPodcast(
                        podcast: $importDto->getPodcast(),
                        channel: $importDto->getChannel()
                    );
                }

                $episodeImportDto = $this->episodeRssImportManager->importEpisode(
                    $importDto->getPodcast(),
                    $importDto->getItem()
                );

                if ($episodeImportDto->isNewlyImported()) {
                    $asset = $episodeImportDto->getEpisode()->getAsset();
                    if ($asset instanceof Asset) {
                        $newlyImportedAssets[] = $asset;
                    }
                    $imported++;
                }
            } catch (Throwable $exception) {
                ++$failed;
                $this->damLogger->error(
                    DamLogger::NAMESPACE_PODCAST_RSS_IMPORT,
                    sprintf('Episode import failed for podcast (%s)', (string) $importDto->getPodcast()->getId()),
                    exception: $exception,
                );

                if (false === $this->entityManager->isOpen()) {
                    // Flush committed iterations' AssetChangedEvent — an idempotent rerun never re-emits them.
                    $this->dispatchNewlyImported($newlyImportedAssets);
                    $this->finishFail($job, $exception);

                    return;
                }
            }

            if ($this->bulkSize === $imported) {
                break;
            }
        }

        $this->finishProcessCycle($lastImportedDto, $imported, $job, $failed);
        $this->dispatchNewlyImported($newlyImportedAssets);
    }

    /**
     * Swallows failures — subscribers touch lazy collections (throw on closed EM) and must never block finishFail().
     *
     * @param array<int, Asset> $newlyImportedAssets
     */
    private function dispatchNewlyImported(array $newlyImportedAssets): void
    {
        if ([] === $newlyImportedAssets) {
            return;
        }

        try {
            $this->assetMetadataBulkEventDispatcher->dispatchAssetChangedEvent(new ArrayCollection($newlyImportedAssets));
        } catch (Throwable $exception) {
            $this->damLogger->error(
                DamLogger::NAMESPACE_PODCAST_RSS_IMPORT,
                sprintf('Asset changed dispatch failed for %d imported episodes', count($newlyImportedAssets)),
                exception: $exception,
            );
        }
    }

    private function finishProcessCycle(?PodcastImportIteratorDto $dto, int $imported, JobPodcastSynchronizer $job, int $failed): void
    {
        if (null === $dto || $imported < $this->bulkSize) {
            $imported = (($this->getManagedJob($job)->getBatchProcessedIterationCount()) * $this->bulkSize) + $imported;
            // Failed episodes are skipped permanently (the pointer advances past them) — surface the count.
            $this->getManagedJob($job)->setResult(sprintf(
                'Podcast job finished. Imported %d episodes%s.',
                $imported,
                $failed > 0 ? sprintf(', %d failed (see logs)', $failed) : App::EMPTY_STRING,
            ));
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
