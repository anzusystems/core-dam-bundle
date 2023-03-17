<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Podcast;

use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\HttpClient\RssClient;
use AnzuSystems\CoreDamBundle\Model\Dto\Podcast\PodcastImportIteratorDto;
use AnzuSystems\CoreDamBundle\Model\Enum\PodcastImportMode;
use AnzuSystems\CoreDamBundle\Model\ValueObject\PodcastSynchronizerPointer;
use AnzuSystems\CoreDamBundle\Repository\PodcastRepository;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use DateTimeImmutable;
use Generator;

final readonly class PodcastImportIterator
{
    public function __construct(
        private RssClient $client,
        private PodcastRssReader $reader,
        private PodcastRepository $podcastRepository,
    ) {
    }

    /**
     * @return Generator<int, PodcastImportIteratorDto>
     *
     * @throws SerializerException
     */
    public function iterate(PodcastSynchronizerPointer $pointer): Generator
    {
        $podcastToImport = $this->getPodcastToImport($pointer);
        if (null === $podcastToImport) {
            return;
        }

        $this->reader->initReader($this->client->readPodcastRss($podcastToImport));
        $startFromDate = $this->getImportFrom($pointer, $podcastToImport);

        while ($podcastToImport) {
            foreach ($this->reader->readItems($startFromDate) as $podcastItem) {
                yield new PodcastImportIteratorDto(
                    podcast: $podcastToImport,
                    item: $podcastItem,
                    channel: $this->reader->readChannel()
                );
            }

            $podcastToImport = $this->getNextPodcast((string) $podcastToImport->getId());
            if ($podcastToImport) {
                $this->reader->initReader($this->client->readPodcastRss($podcastToImport));
                $startFromDate = $podcastToImport->getDates()->getImportFrom();
            }
        }
    }

    /**
     * @return Generator<int, PodcastImportIteratorDto>
     *
     * @throws SerializerException
     */
    public function iteratePodcast(PodcastSynchronizerPointer $pointer, Podcast $podcastToImport): Generator
    {
        $this->reader->initReader($this->client->readPodcastRss($podcastToImport));
        $startFromDate = $this->getImportFrom($pointer, $podcastToImport);

        foreach ($this->reader->readItems($startFromDate) as $podcastItem) {
            yield new PodcastImportIteratorDto(
                podcast: $podcastToImport,
                item: $podcastItem,
                channel: $this->reader->readChannel()
            );
        }
    }

    private function getImportFrom(PodcastSynchronizerPointer $pointer, Podcast $podcast): ?DateTimeImmutable
    {
        if (null === $podcast->getDates()->getImportFrom()) {
            return $pointer->getPubDate();
        }

        if (null === $pointer->getPubDate()) {
            return $podcast->getDates()->getImportFrom();
        }

        return $podcast->getDates()->getImportFrom() > $pointer->getPubDate()
            ? $podcast->getDates()->getImportFrom()
            : $pointer->getPubDate();
    }

    private function getPodcastToImport(PodcastSynchronizerPointer $pointer): ?Podcast
    {
        return $pointer->getPodcastId()
            ? $this->podcastRepository->find($pointer->getPodcastId())
            : $this->getNextPodcast();
    }

    private function getNextPodcast(?string $podcastId = null): ?Podcast
    {
        return $this->podcastRepository->findOneFrom($podcastId, PodcastImportMode::Import);
    }
}
