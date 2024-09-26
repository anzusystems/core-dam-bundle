<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Podcast;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Exception\InvalidArgumentException;
use AnzuSystems\CoreDamBundle\HttpClient\RssClient;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Dto\Podcast\PodcastImportIteratorDto;
use AnzuSystems\CoreDamBundle\Model\Enum\PodcastImportMode;
use AnzuSystems\CoreDamBundle\Model\ValueObject\PodcastSynchronizerPointer;
use AnzuSystems\CoreDamBundle\Repository\PodcastRepository;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use DateTimeImmutable;
use Generator;

final readonly class PodcastImportIterator
{
    private const string MIN_IMPORT_FROM_MODIFIER = '- 3 months';

    public function __construct(
        private RssClient $client,
        private PodcastRssReader $reader,
        private PodcastRepository $podcastRepository,
        private DamLogger $damLogger,
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

        while ($podcastToImport) {
            foreach ($this->iteratePodcast($pointer, $podcastToImport) as $item) {
                yield $item;
            }

            $podcastToImport = $this->getNextPodcast((string) $podcastToImport->getId());
            if ($podcastToImport) {
                $pointer = (new PodcastSynchronizerPointer(
                    $podcastToImport->getId(),
                    null
                ));
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
        try {
            $this->reader->initReader($this->client->readPodcastRss($podcastToImport));
        } catch (InvalidArgumentException $exception) {
            $this->damLogger->error(
                DamLogger::NAMESPACE_PODCAST_RSS_IMPORT,
                sprintf(
                    'Invalid RSS XML from URL (%s)',
                    $podcastToImport->getAttributes()->getRssUrl()
                ),
                $exception
            );

            return;
        }
        $startFromDate = $this->getImportFrom($pointer);

        foreach ($this->reader->readItems($startFromDate) as $podcastItem) {
            yield new PodcastImportIteratorDto(
                podcast: $podcastToImport,
                item: $podcastItem,
                channel: $this->reader->readChannel()
            );
        }
    }

    private function getImportFrom(PodcastSynchronizerPointer $pointer): ?DateTimeImmutable
    {
        $minImportFrom = App::getAppDate()->modify(self::MIN_IMPORT_FROM_MODIFIER);

        if (null === $pointer->getPubDate()) {
            return $minImportFrom;
        }

        return $minImportFrom > $pointer->getPubDate()
            ? $minImportFrom
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
