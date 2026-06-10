<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Podcast;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Exception\InvalidArgumentException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Dto\RssFeed\Channel;
use AnzuSystems\CoreDamBundle\Model\Dto\RssFeed\ChannelItunes;
use AnzuSystems\CoreDamBundle\Model\Dto\RssFeed\Item;
use AnzuSystems\CoreDamBundle\Model\Dto\RssFeed\ItemEnclosure;
use AnzuSystems\CoreDamBundle\Model\Dto\RssFeed\ItemItunes;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use DateTimeImmutable;
use DOMDocument;
use DOMNode;
use Exception;
use Generator;
use SimpleXMLElement;
use Throwable;
use XMLReader;

final class PodcastRssReader
{
    public const string RSS_DATE_FORMAT = 'D, d M Y H:i:s T';
    private const string ITUNES_KEY_KEY = 'itunes';
    private const string ITEM_ELEMENT = 'item';

    private string $content = '';

    public function __construct(
        private readonly DamLogger $logger,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function initReader(string $content): void
    {
        $this->content = $content;

        $reader = $this->openReader();

        try {
            $hasChannel = false;
            $hasItem = false;
            while ($reader->read()) {
                if (XMLReader::ELEMENT !== $reader->nodeType) {
                    continue;
                }
                if ('channel' === $reader->localName) {
                    $hasChannel = true;
                }
                if (self::ITEM_ELEMENT === $reader->localName) {
                    $hasItem = true;

                    break;
                }
            }
        } finally {
            $reader->close();
        }

        if (false === $hasChannel) {
            throw new InvalidArgumentException(message: 'Invalid XML content, channel missing');
        }
        if (false === $hasItem) {
            throw new InvalidArgumentException(message: 'Invalid XML content, channel item missing');
        }
    }

    public function readChannel(): Channel
    {
        $channelXml = $this->parseHeader()->channel;

        $channel = (new Channel())
            ->setTitle((string) $channelXml?->title)
            ->setDescription((string) $channelXml?->description)
            ->setLanguage((string) $channelXml?->language)
        ;

        $itunesXml = $channelXml?->children(self::ITUNES_KEY_KEY, true);

        if ($itunesXml) {
            $channelItunes = (new ChannelItunes())
                ->setImage((string) $itunesXml->image?->attributes()?->href)
                ->setExplicit((string) $itunesXml->explicit);

            foreach ($itunesXml->category ?? [] as $category) {
                $channelItunes->addCategory((string) $category->attributes()?->text);
            }

            $channel->setItunes($channelItunes);
        }

        return $channel;
    }

    /**
     * Streams items one at a time (no whole-feed DOM); buffers only the light DTOs to keep reverse order.
     *
     * @throws SerializerException
     * @throws Exception
     */
    public function readItems(?DateTimeImmutable $from = null): Generator
    {
        $reader = $this->openReader();
        $items = [];

        try {
            while ($reader->read()) {
                if (XMLReader::ELEMENT === $reader->nodeType && self::ITEM_ELEMENT === $reader->localName) {
                    break;
                }
            }

            while (XMLReader::ELEMENT === $reader->nodeType && self::ITEM_ELEMENT === $reader->localName) {
                $element = $this->expandItem($reader);
                if ($element instanceof SimpleXMLElement) {
                    $item = $this->readItem($element);
                    if (false === ($item->getPubDate() && $from && $from > $item->getPubDate())) {
                        $items[] = $item;
                    }
                }

                $reader->next(self::ITEM_ELEMENT);
            }
        } finally {
            $reader->close();
        }

        foreach (array_reverse($items) as $item) {
            yield $item;
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function openReader(): XMLReader
    {
        if (App::EMPTY_STRING === $this->content) {
            throw new InvalidArgumentException(message: 'Invalid XML content');
        }

        $reader = XMLReader::XML($this->content);
        if (false === $reader) {
            throw new InvalidArgumentException(message: 'Invalid XML content');
        }

        return $reader;
    }

    /**
     * Parses only the channel header (everything before the first <item>) — small, never the whole feed.
     *
     * @throws InvalidArgumentException
     */
    private function parseHeader(): SimpleXMLElement
    {
        $headerXml = $this->content;
        if (preg_match('/<' . self::ITEM_ELEMENT . '[\s>]/i', $this->content, $match, PREG_OFFSET_CAPTURE)) {
            $headerXml = substr($this->content, 0, (int) $match[0][1]) . '</channel></rss>';
        }

        try {
            return new SimpleXMLElement($headerXml);
        } catch (Throwable $e) {
            throw new InvalidArgumentException(message: 'Invalid XML content', previous: $e);
        }
    }

    private function expandItem(XMLReader $reader): ?SimpleXMLElement
    {
        $node = $reader->expand(new DOMDocument());
        if (false === $node instanceof DOMNode) {
            return null;
        }

        $element = simplexml_import_dom($node);

        return $element instanceof SimpleXMLElement ? $element : null;
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

        $enclosureAttributes = $element->enclosure?->attributes();
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
                    ->setImage((string) $itunes->image?->attributes()?->href)
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
            self::RSS_DATE_FORMAT,
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
