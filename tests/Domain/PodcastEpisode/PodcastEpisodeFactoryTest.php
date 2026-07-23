<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\PodcastEpisode;

use AnzuSystems\CoreDamBundle\DataFixtures\AudioFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\PodcastFixtures;
use AnzuSystems\CoreDamBundle\Domain\PodcastEpisode\PodcastEpisodeFactory;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsAudioStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\VoiceDiscriminator;
use AnzuSystems\CoreDamBundle\Repository\VoiceFamilyRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ImageFixtures;
use PHPUnit\Framework\Attributes\DataProvider;

// #86101: episodes inherit the TTS asset's main image as cover; an unusable image is skipped, never fatal.
final class PodcastEpisodeFactoryTest extends CoreDamKernelTestCase
{
    private PodcastEpisodeFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->getService(PodcastEpisodeFactory::class);
    }

    public function testEpisodeInheritsCoverFromTtsAsset(): void
    {
        $asset = $this->audioAsset();
        $image = $this->processedImageInLicence($asset->getLicence());
        $this->giveTtsAsset($asset, (string) $image->getId());

        $episode = $this->addToPodcast($asset);

        $imagePreview = $episode->getImagePreview();
        self::assertNotNull($imagePreview, 'Episode must inherit the TTS asset main image as its cover.');
        self::assertSame((string) $image->getId(), (string) $imagePreview->getImageFile()->getId());
    }

    // A bad image id degrades to "no cover", never to an exception.
    #[DataProvider('provideSkippedCovers')]
    public function testEpisodeGetsNoCoverWhenImageIsUnusable(?string $mainImageFileId): void
    {
        $asset = $this->audioAsset();
        $this->giveTtsAsset($asset, $mainImageFileId);

        self::assertNull($this->addToPodcast($asset)->getImagePreview());
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function provideSkippedCovers(): iterable
    {
        yield 'caller supplied no image' => [null];
        yield 'unknown image id' => ['3fa85f64-5717-4562-b3fc-2c963f66afa6'];
        yield 'id of an audio file, not an image' => [AudioFixtures::AUDIO_ID_1];
    }

    public function testNonTtsAssetGetsNoCover(): void
    {
        // No TtsAsset at all — the generic membership path must not blow up looking for one.
        self::assertNull($this->addToPodcast($this->audioAsset())->getImagePreview());
    }

    public function testImageFromAnotherLicenceIsSkipped(): void
    {
        $asset = $this->audioAsset();
        $foreignLicence = $this->entityManager->find(AssetLicence::class, AssetLicenceFixtures::LICENCE_2_ID);
        self::assertInstanceOf(AssetLicence::class, $foreignLicence);
        self::assertNotSame($asset->getLicence()->getId(), $foreignLicence->getId());

        $foreignImage = $this->processedImageInLicence($foreignLicence);
        $this->giveTtsAsset($asset, (string) $foreignImage->getId());

        self::assertNull(
            $this->addToPodcast($asset)->getImagePreview(),
            'A cross-licence image must never leak onto an episode cover.',
        );
    }

    public function testReconcileBackfillsEpisodeCreatedBeforeAudioExisted(): void
    {
        $asset = $this->audioAsset();
        $image = $this->processedImageInLicence($asset->getLicence());
        $this->giveTtsAsset($asset, (string) $image->getId());
        // Mimics the real hole: membership added while the narration was still rendering.
        $episode = $this->addToPodcast($asset, inheritFromAsset: false);
        self::assertNull($episode->getImagePreview());
        self::assertSame(0, $episode->getAttributes()->getDuration());

        $this->factory->reconcileEpisodesFromAsset($asset);

        self::assertNotNull($episode->getImagePreview(), 'Reconcile must backfill the missing cover.');
        self::assertSame(
            $this->expectedDuration($asset),
            $episode->getAttributes()->getDuration(),
            'Reconcile must backfill duration from the asset audio.',
        );
    }

    public function testReconcileNeverOverwritesAnExistingCover(): void
    {
        $asset = $this->audioAsset();
        $image = $this->processedImageInLicence($asset->getLicence());
        $this->giveTtsAsset($asset, (string) $image->getId());
        $episode = $this->addToPodcast($asset);
        $ownCover = $episode->getImagePreview();
        self::assertNotNull($ownCover);

        $this->factory->reconcileEpisodesFromAsset($asset);

        self::assertSame($ownCover, $episode->getImagePreview(), 'Fill-if-empty must leave a set cover untouched.');
    }

    public function testReconcileLeavesEpisodeNumberUntouched(): void
    {
        $asset = $this->audioAsset();
        $image = $this->processedImageInLicence($asset->getLicence());
        $this->giveTtsAsset($asset, (string) $image->getId());
        $episode = $this->addToPodcast($asset, inheritFromAsset: false);
        $this->giveEpisodeNumber($episode, 42);

        $this->factory->reconcileEpisodesFromAsset($asset);

        self::assertSame(42, $episode->getAttributes()->getEpisodeNumber());
    }

    // The audio fixture already carries episodes — assert on the returned episode, not a guessed one.
    private function addToPodcast(Asset $asset, bool $inheritFromAsset = true): PodcastEpisode
    {
        $podcast = $this->entityManager->find(Podcast::class, PodcastFixtures::PODCAST_1);
        self::assertInstanceOf(Podcast::class, $podcast);

        return $this->factory->createEpisodeWithAsset($asset, $podcast, flush: true, inheritFromAsset: $inheritFromAsset);
    }

    private function audioAsset(): Asset
    {
        $audio = $this->entityManager->find(AudioFile::class, AudioFixtures::AUDIO_ID_1);
        self::assertInstanceOf(AudioFile::class, $audio);

        return $audio->getAsset();
    }

    private function giveEpisodeNumber(PodcastEpisode $episode, int $episodeNumber): void
    {
        $episode->getAttributes()->setEpisodeNumber($episodeNumber);
    }

    private function giveTtsAsset(Asset $asset, ?string $mainImageFileId): TtsAsset
    {
        $voiceFamily = $this->getService(VoiceFamilyRepository::class)->findOneBy([]);
        self::assertInstanceOf(VoiceFamily::class, $voiceFamily);

        $ttsAsset = (new TtsAsset($asset))
            ->setVoiceFamily($voiceFamily)
            ->setProvider(VoiceDiscriminator::Elevenlabs)
            ->setExternalVoiceId('test-voice')
            ->setSourceTextHash(hash('sha256', 'text'))
            ->setSourceTextSnapshot('text')
            ->setMainImageFileId($mainImageFileId)
            ->setStatus(TtsAudioStatus::Active);

        return $this->getService(TtsAssetManager::class)->create($ttsAsset, flush: true);
    }

    // Fixture image licences don't match the audio fixture's — pin the licence explicitly.
    private function processedImageInLicence(AssetLicence $licence): ImageFile
    {
        $image = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1);
        self::assertInstanceOf(ImageFile::class, $image);

        $image->setLicence($licence);
        $image->getAsset()->setLicence($licence);
        $image->getAssetAttributes()->setStatus(AssetFileProcessStatus::Processed);
        $this->entityManager->flush();

        return $image;
    }

    private function expectedDuration(Asset $asset): int
    {
        $mainFile = $asset->getMainFile();
        self::assertInstanceOf(AudioFile::class, $mainFile);

        return $mainFile->getAttributes()->getDuration();
    }
}
