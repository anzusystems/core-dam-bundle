<?php

declare(strict_types=1);


namespace AnzuSystems\CoreDamBundle\Domain\Podcast;

use AnzuSystems\CoreDamBundle\Domain\PodcastEpisode\EpisodeRssImportManager;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\HttpClient\RssClient;
use AnzuSystems\CoreDamBundle\Model\Dto\Podcast\PodcastImportIteratorDto;
use AnzuSystems\CoreDamBundle\Model\Enum\PodcastImportMode;
use AnzuSystems\CoreDamBundle\Model\ValueObject\PodcastSynchronizerPointer;
use AnzuSystems\CoreDamBundle\Repository\PodcastRepository;
use Generator;

final readonly class PodcastImportIterator
{
    public function __construct(
        private RssImportManager $rssImportManager,
        private EpisodeRssImportManager $episodeRssImportManager,
        private RssClient $client,
        private PodcastRssReader $reader,
        private PodcastRepository $podcastRepository,
    ) {
    }

    /**
     * @return Generator<int, PodcastImportIteratorDto>
     */
    public function iterate(PodcastSynchronizerPointer $pointer): Generator
    {
        // todo get next Podcast (order by updatedAt?)
        $podcastToImport = $this->getPodcastToImport($pointer);
        $this->reader->initReader($this->client->readPodcastRss($podcastToImport));
        $startFromDate = $pointer->getPubDate(); // todo importFrom

        while ($podcastToImport) {
            foreach ($this->reader->readItems($startFromDate) as $podcastItem) {
                yield new PodcastImportIteratorDto(
                    podcast: $podcastToImport,
                    item: $podcastItem
                );
            }

            $podcastToImport = $this->getNextPodcast((string) $podcastToImport->getId());
            if (null === $podcastToImport) {
                break;
            }
            $this->reader->initReader($this->client->readPodcastRss($podcastToImport));
            $startFromDate = null;
        }
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