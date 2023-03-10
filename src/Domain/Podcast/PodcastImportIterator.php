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
    public function iterate(PodcastSynchronizerPointer $pointer, int $bulkSize = 2): Generator
    {
        // todo get next Podcast (order by updatedAt?)

//        $this->reader->initReader($this->client->readPodcastRss($podcast));

        $imported = 0;
        $podcastId = $pointer->getPodcastId();

        $podcastToImport = $this->getPodcastToImport($pointer);
        $podcastGuid = $pointer->getEpisodeGuid();

        $this->reader->initReader($this->client->readPodcastRss($podcastToImport));

        while ($podcastToImport) {
            foreach ($this->reader->readItems($podcastGuid, $podcastToImport->getDates()->getImportFrom()) as $podcastItem) {
                $imported++;

                dump(sprintf('Importing podcast (%s) guid (%s)', $podcastToImport->getTexts()->getTitle(), $podcastItem->getGuid()));

                yield new PodcastImportIteratorDto(
                    podcast: $podcastToImport,
                    item: $podcastItem
                );

                if ($imported === $bulkSize) {
                    break;
                }
            }

            $podcastToImport = $this->getNextPodcast((string) $podcastToImport->getId());

            if ($imported === $bulkSize) {
                break;
            }
        }






//        $lastId = $pointer->getPodcastId();
//        while ($podcast = $this->podcastRepository->findOneFrom($lastId, PodcastImportMode::Import)) {
//
//        }
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