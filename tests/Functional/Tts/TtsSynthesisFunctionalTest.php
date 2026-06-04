<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Functional\Tts;

use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Domain\Tts\Config;
use AnzuSystems\CoreDamBundle\Domain\Tts\Facade\TtsDispatchFacade;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Messenger\Handler\TtsNarrationRequestHandler;
use AnzuSystems\CoreDamBundle\Messenger\Message\TtsNarrationRequestMessage;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsSynthesizeRequestDto;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsAudioStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Repository\TtsAssetRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\TtsVoiceFixtures;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * End-to-end TTS pipeline over real fixtures + mocked providers:
 * request → voice resolve → ElevenLabs (mock returns sample MP3 bytes) → ffmpeg concat → store → asset.
 * Asserts the concatenated master audio duration so a broken chunk/concat step is caught.
 */
final class TtsSynthesisFunctionalTest extends CoreDamKernelTestCase
{
    private TtsDispatchFacade $dispatchFacade;
    private TtsNarrationRequestHandler $planHandler;
    private TtsAssetRepository $ttsAssetRepo;
    private AssetRepository $assetRepo;
    private Config $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatchFacade = $this->getService(TtsDispatchFacade::class);
        $this->planHandler = $this->getService(TtsNarrationRequestHandler::class);
        $this->ttsAssetRepo = $this->getService(TtsAssetRepository::class);
        $this->assetRepo = $this->getService(AssetRepository::class);
        $this->config = $this->getService(Config::class);
    }

    public function testMultiChunkConcatMatchesExpectedDuration(): void
    {
        // Single chunk → one mocked MP3 → baseline duration.
        $single = $this->dispatchAndProcess('A single short narration sentence.');
        $singleDuration = $this->masterDuration($single);
        self::assertGreaterThan(0, $singleDuration, 'Master audio duration must be extracted.');
        self::assertTrue($this->ttsStatus($single)->is(TtsAudioStatus::Active));

        // Two ~3.5k-char sentences → combined > 4800 effective chunk size → exactly two chunks → concat of
        // two identical mocked MP3s → ~double the duration. Verifies the ffmpeg concat actually concatenated.
        $sentence = str_repeat('veta ', 700) . '.';
        $twoChunkText = $sentence . ' ' . $sentence;
        $multi = $this->dispatchAndProcess($twoChunkText);
        $multiDuration = $this->masterDuration($multi);

        self::assertLessThanOrEqual(
            1,
            abs($multiDuration - (2 * $singleDuration)),
            sprintf('Two-chunk concat duration (%ds) should be ~2x the single-chunk duration (%ds).', $multiDuration, $singleDuration),
        );
    }

    private function dispatchAndProcess(string $text): Asset
    {
        $licence = $this->entityManager->find(AssetLicence::class, AssetLicenceFixtures::DEFAULT_LICENCE_ID);
        self::assertInstanceOf(AssetLicence::class, $licence);

        $dto = (new TtsSynthesizeRequestDto())
            ->setText($text)
            ->setAssetLicence($licence)
            ->setVoiceFamilySlug(TtsVoiceFixtures::DEFAULT_FAMILY_SLUG)
            ->setPodcasts(new ArrayCollection());

        $result = $this->dispatchFacade->synthesize($dto, enqueue: false);
        self::assertNotNull($result->narrationRequest, 'Initial dispatch should produce a request.');

        // Drive the real worker entry: claims the request → plans → single chunk runs inline, multi-chunk
        // fans out per-chunk messages (handled synchronously in tests, no async transport).
        ($this->planHandler)(new TtsNarrationRequestMessage((string) $result->narrationRequest->getId()));

        $asset = $this->assetRepo->find((string) $result->getAssetId());
        self::assertInstanceOf(Asset::class, $asset);

        return $asset;
    }

    private function masterDuration(Asset $asset): int
    {
        foreach ($asset->getSlots() as $slot) {
            if ($slot->getName() === $this->config->getMasterSlotName()) {
                $audio = $slot->getAudio();
                self::assertNotNull($audio, 'Master slot must hold an audio file.');

                return $audio->getAttributes()->getDuration();
            }
        }

        self::fail('Master audio slot not found on asset.');
    }

    private function ttsStatus(Asset $asset): TtsAudioStatus
    {
        $ttsAsset = $this->ttsAssetRepo->findByAsset($asset);
        self::assertNotNull($ttsAsset);

        return $ttsAsset->getStatus();
    }
}
