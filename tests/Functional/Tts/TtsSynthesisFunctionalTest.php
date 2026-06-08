<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Functional\Tts;

use AnzuSystems\CoreDamBundle\Domain\Tts\Config;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Messenger\Handler\TtsNarrationRequestHandler;
use AnzuSystems\CoreDamBundle\Messenger\Handler\TtsSynthChunkHandler;
use AnzuSystems\CoreDamBundle\Messenger\Message\TtsNarrationRequestMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\TtsSynthChunkMessage;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsAudioStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Repository\TtsAssetRepository;
use AnzuSystems\CoreDamBundle\Repository\TtsNarrationRequestRepository;
use AnzuSystems\CoreDamBundle\Repository\TtsSynthesisChunkRepository;
use AnzuSystems\CoreDamBundle\Tests\HttpClient\ElevenlabsClientMock;

/**
 * End-to-end TTS pipeline over real fixtures + mocked providers:
 * request → voice resolve → ElevenLabs (mock returns sample MP3 bytes) → ffmpeg concat → store → asset.
 * Asserts the concatenated master audio duration so a broken chunk/concat step is caught.
 */
final class TtsSynthesisFunctionalTest extends AbstractTtsFunctionalTestCase
{
    private TtsNarrationRequestHandler $planHandler;
    private TtsSynthChunkHandler $chunkHandler;
    private TtsSynthesisChunkRepository $chunkRepo;
    private TtsAssetRepository $ttsAssetRepo;
    private AssetRepository $assetRepo;
    private TtsNarrationRequestRepository $requestRepo;
    private Config $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planHandler = $this->getService(TtsNarrationRequestHandler::class);
        $this->chunkHandler = $this->getService(TtsSynthChunkHandler::class);
        $this->chunkRepo = $this->getService(TtsSynthesisChunkRepository::class);
        $this->ttsAssetRepo = $this->getService(TtsAssetRepository::class);
        $this->assetRepo = $this->getService(AssetRepository::class);
        $this->requestRepo = $this->getService(TtsNarrationRequestRepository::class);
        $this->config = $this->getService(Config::class);
    }

    /**
     * Provider failure path: the chunk synth returns 500 → the chunk fails → the request is marked Failed and
     * its reserved (file-less) asset is dropped so it doesn't shadow future dispatch idempotency.
     */
    public function testFailedSynthesisMarksRequestFailedAndCleansUpAsset(): void
    {
        $result = $this->dispatchFacade->synthesize(
            $this->buildSynthesizeDto(ElevenlabsClientMock::FORCE_FAIL_MARKER . ' a narration that fails.'),
            enqueue: false,
        );
        self::assertNotNull($result->narrationRequest, 'Initial dispatch should produce a request.');
        $requestId = (string) $result->narrationRequest->getId();
        $reservedAssetId = (string) $result->getAssetId();

        // Real worker entry: plan + drive the chunk chain; the synth hits the mocked 500.
        $this->drivePipeline($requestId);

        $request = $this->requestRepo->find($requestId);
        self::assertInstanceOf(TtsNarrationRequest::class, $request);
        self::assertTrue(
            $request->getStatus()->is(TtsRequestStatus::Failed),
            'A provider synthesis failure must mark the request Failed.',
        );
        self::assertNull(
            $this->assetRepo->find($reservedAssetId),
            'A failed initial synthesis must drop its reserved asset.',
        );
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
        $result = $this->dispatchFacade->synthesize($this->buildSynthesizeDto($text), enqueue: false);
        self::assertNotNull($result->narrationRequest, 'Initial dispatch should produce a request.');

        $this->drivePipeline((string) $result->narrationRequest->getId());

        $asset = $this->assetRepo->find((string) $result->getAssetId());
        self::assertInstanceOf(Asset::class, $asset);

        return $asset;
    }

    /**
     * Real worker entry over the sync transport: plan the request, then drive the per-chunk chain inline
     * (messages aren't auto-consumed in tests; the last chunk assembles + finalizes) until none stay pending.
     */
    private function drivePipeline(string $requestId): void
    {
        ($this->planHandler)(new TtsNarrationRequestMessage($requestId));
        while (null !== ($chunk = $this->chunkRepo->findNextPending($requestId))) {
            ($this->chunkHandler)(new TtsSynthChunkMessage((string) $chunk->getId()));
        }
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
