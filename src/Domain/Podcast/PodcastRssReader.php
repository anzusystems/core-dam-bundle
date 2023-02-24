<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Podcast;

use AnzuSystems\CoreDamBundle\Exception\InvalidArgumentException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Dto\RssFeed\Channel;
use AnzuSystems\CoreDamBundle\Model\Dto\RssFeed\ChannelItunes;
use AnzuSystems\CoreDamBundle\Model\Dto\RssFeed\Item;
use AnzuSystems\CoreDamBundle\Model\Dto\RssFeed\ItemEnclosure;
use AnzuSystems\CoreDamBundle\Model\Dto\RssFeed\ItemItunes;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use DateTimeImmutable;
use Exception;
use Generator;
use SimpleXMLElement;
use Throwable;

final class PodcastRssReader
{
    private const ITUNES_KEY_KEY = 'itunes';

    private SimpleXMLElement $body;

    public function __construct(
        private readonly DamLogger $logger,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function initReader(string $content): void
    {
        try {
            $this->body = new SimpleXMLElement($content);
        } catch (Throwable $e) {
            throw new InvalidArgumentException(
                message: 'Invalid XML content',
                previous: $e
            );
        }

        if (false === isset($this->body->channel)) {
            throw new InvalidArgumentException(
                message: 'Invalid XML content, channel missing'
            );
        }

        if (false === $this->body->channel->item) {
            throw new InvalidArgumentException(
                message: 'Invalid XML content, channel item missing'
            );
        }
    }

    public function readChannel(): Channel
    {
        $channelXml = $this->body->channel;

        $channel = (new Channel())
            ->setTitle((string) $channelXml->title)
            ->setDescription((string) $channelXml->description)
            ->setLanguage((string) $channelXml->language)
        ;

        $itunesXml = $channelXml->children(self::ITUNES_KEY_KEY, true);

        if ($itunesXml) {
            $channelItunes = (new ChannelItunes())
                ->setImage((string) $itunesXml->image->attributes()?->href)
                ->setExplicit((string) $itunesXml->explicit);

            foreach ($itunesXml->category as $category) {
                $channelItunes->addCategory((string) $category->attributes()?->text);
            }

            $channel->setItunes($channelItunes);
        }

        return $channel;
    }

    /**
     * @throws SerializerException
     * @throws Exception
     */
    public function readItems(?string $startFromGuid = null): Generator
    {
        foreach (array_reverse($this->body->channel->xpath('item')) as $item) {
            if ($startFromGuid) {
                if ((string) $item->guid === $startFromGuid) {
                    $startFromGuid = null;
                }

                continue;
            }
            yield $this->readItem($item);
        }
    }

    /**
     * @throws SerializerException
     */
    private function readItem(SimpleXMLElement $element): Item
    {
        $item = (new Item())
            ->setTitle((string) $element->title)
            ->setDescription((string) $element->description)
            ->setLink((string) $element->link)
            ->setGuid((string) $element->guid)
            ->setPubDate($this->getPublicationDate($element))
        ;

        $enclosureAttributes = $element->enclosure->attributes();
        if ($enclosureAttributes) {
            $item->setEnclosure(
                (new ItemEnclosure())
                    ->setType((string) $enclosureAttributes->type)
                    ->setUrl((string) $enclosureAttributes->url)
            );
        }

        $itunes = $element->children(self::ITUNES_KEY_KEY, true);

        if ($itunes) {
            $item->setItunes(
                (new ItemItunes())
                    ->setEpisode((string) $itunes->episode)
                    ->setSeason((string) $itunes->season)
                    ->setEpisodeType((string) $itunes->episodeType)
                    ->setExplicit((string) $itunes->explicit)
                    ->setDuration((string) $itunes->duration)
                    ->setImage((string) $itunes->image->attributes()?->href)
            );
        }

        return $item;
    }

    /**
     * @throws SerializerException
     */
    private function getPublicationDate(SimpleXMLElement $element): ?DateTimeImmutable
    {
        $publicationDateString = (string) $element->pubDate;
        $publicationDate = DateTimeImmutable::createFromFormat(
            'D, d M Y H:i:s T',
            $publicationDateString,
        );

        if ($publicationDate) {
            return $publicationDate;
        }

        $this->logger->error(
            DamLogger::NAMESPACE_PODCAST_RSS_IMPORT,
            "Invalid publication date format ({$publicationDateString})"
        );

        return null;
    }
}
