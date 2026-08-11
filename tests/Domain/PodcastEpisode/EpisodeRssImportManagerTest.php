<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\PodcastEpisode;

use AnzuSystems\CoreDamBundle\DataFixtures\PodcastEpisodeFixtures;
use AnzuSystems\CoreDamBundle\Domain\PodcastEpisode\EpisodeRssImportManager;
use AnzuSystems\CoreDamBundle\Entity\AssetSlot;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\CoreDamBundle\Event\PodcastEpisodeAudioAdoptedEvent;
use AnzuSystems\CoreDamBundle\Model\Dto\RssFeed\Item;
use AnzuSystems\CoreDamBundle\Model\Dto\RssFeed\ItemEnclosure;
use AnzuSystems\CoreDamBundle\Model\Enum\PodcastEpisodeStatus;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

// #86356: an editor uploads the audio into the import slot; the nightly feed must publish it, not fail it.
final class EpisodeRssImportManagerTest extends CoreDamKernelTestCase
{
    private const string ENCLOSURE_URL = 'https://feed.local/rano-1.mp3';

    public function testEditorialFileInImportSlotIsAdoptedAndAnnounced(): void
    {
        $episode = $this->givenEpisodeWithEditorialAudio(episodeRssUrl: '');
        $adopted = null;
        $this->getService(EventDispatcherInterface::class)->addListener(
            PodcastEpisodeAudioAdoptedEvent::class,
            static function (PodcastEpisodeAudioAdoptedEvent $event) use (&$adopted): void {
                $adopted = $event;
            }
        );

        $dto = $this->getService(EpisodeRssImportManager::class)->importEpisode($episode->getPodcast(), $this->givenItem($episode));

        self::assertTrue($dto->isNewlyImported());
        self::assertSame(PodcastEpisodeStatus::Imported, $episode->getAttributes()->getLastImportStatus());
        self::assertSame(self::ENCLOSURE_URL, $episode->getAttributes()->getRssUrl());
        self::assertTrue($episode->getFlags()->isFromRss());
        self::assertInstanceOf(PodcastEpisodeAudioAdoptedEvent::class, $adopted);
        // The editorial file keeps its provenance — nothing was downloaded for it.
        self::assertEmpty($adopted->getAudioFile()->getAssetAttributes()->getOriginUrl());
    }

    public function testSecondRunOfTheSameFeedItemAdoptsNothingAgain(): void
    {
        $episode = $this->givenEpisodeWithEditorialAudio(episodeRssUrl: '');
        $manager = $this->getService(EpisodeRssImportManager::class);
        $manager->importEpisode($episode->getPodcast(), $this->givenItem($episode));
        $episode->getTexts()->setDescription('Editor wrote this after the first import');

        $dto = $manager->importEpisode($episode->getPodcast(), $this->givenItem($episode));

        self::assertFalse($dto->isNewlyImported());
        self::assertSame(PodcastEpisodeStatus::Imported, $episode->getAttributes()->getLastImportStatus());
        self::assertSame('Editor wrote this after the first import', $episode->getTexts()->getDescription());
    }

    public function testEpisodeAlreadyFedFromRssStaysAConflict(): void
    {
        $episode = $this->givenEpisodeWithEditorialAudio(episodeRssUrl: 'https://feed.local/previous.mp3');

        $dto = $this->getService(EpisodeRssImportManager::class)->importEpisode($episode->getPodcast(), $this->givenItem($episode));

        self::assertFalse($dto->isNewlyImported());
        self::assertSame(PodcastEpisodeStatus::ImportFailed, $episode->getAttributes()->getLastImportStatus());
    }

    private function givenEpisodeWithEditorialAudio(string $episodeRssUrl): PodcastEpisode
    {
        $episode = $this->entityManager->find(PodcastEpisode::class, PodcastEpisodeFixtures::EPISODE_1_ID);
        self::assertInstanceOf(PodcastEpisode::class, $episode);
        $slot = $episode->getAsset()?->getSlots()->first();
        self::assertInstanceOf(AssetSlot::class, $slot);

        $episode->getPodcast()->getAttributes()->setFileSlot($slot->getName());
        $episode->getAttributes()->setRssUrl($episodeRssUrl);
        $slot->getAssetFile()->getAssetAttributes()->setOriginUrl(null);

        return $episode;
    }

    private function givenItem(PodcastEpisode $episode): Item
    {
        return (new Item())
            ->setTitle($episode->getTexts()->getTitle())
            ->setEnclosure((new ItemEnclosure())->setUrl(self::ENCLOSURE_URL));
    }
}
