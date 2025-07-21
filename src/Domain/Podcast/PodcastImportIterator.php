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

final class PodcastImportIterator
{
    private const string MIN_IMPORT_FROM_MODIFIER = '- 3 months';

    public function __construct(
        private readonly RssClient $client,
        private readonly PodcastRssReader $reader,
        private readonly PodcastRepository $podcastRepository,
        private readonly DamLogger $damLogger,
    ) {
    }

    /**
     * @return Generator<int, PodcastImportIteratorDto>
     *
     * @throws SerializerException
     */
    public function iterate(PodcastSynchronizerPointer $pointer, ?DateTimeImmutable $minImportFrom = null): Generator
    {
        $podcastToImport = $this->getPodcastToImport($pointer);
        if (null === $podcastToImport) {
            return;
        }

        while ($podcastToImport) {
            foreach ($this->iteratePodcast($pointer, $podcastToImport, $minImportFrom) as $item) {
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
    public function iteratePodcast(PodcastSynchronizerPointer $pointer, Podcast $podcastToImport, ?DateTimeImmutable $minImportFrom = null): Generator
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
        $startFromDate = $this->getImportFrom($pointer, $podcastToImport->getDates()->getImportFrom() ?? $minImportFrom);

        foreach ($this->reader->readItems($startFromDate) as $podcastItem) {
            yield new PodcastImportIteratorDto(
                podcast: $podcastToImport,
                item: $podcastItem,
                channel: $this->reader->readChannel()
            );
        }
    }

    private function getImportFrom(PodcastSynchronizerPointer $pointer, ?DateTimeImmutable $minImportFrom): ?DateTimeImmutable
    {
        $minImportFrom = $minImportFrom ?? App::getAppDate()->modify(self::MIN_IMPORT_FROM_MODIFIER);

        if (false === ($pointer->getPubDate() instanceof DateTimeImmutable)) {
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
