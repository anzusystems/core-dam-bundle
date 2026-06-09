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

/** End-to-end TTS pipeline: dispatch → chunk → ElevenLabs mock → concat → assert duration. */
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
     * Provider 500 → request marked Failed, reserved asset dropped.
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
        $single = $this->dispatchAndProcess('A single short narration sentence.');
        $singleDuration = $this->masterDuration($single);
        self::assertGreaterThan(0, $singleDuration, 'Master audio duration must be extracted.');
        self::assertTrue($this->ttsStatus($single)->is(TtsAudioStatus::Active));

        // Two ~3.5k-char sentences force exactly two chunks; concat should yield ~2× duration.
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

    public function testRegenerateViaSysDispatchUpdatesSnapshotAndHash(): void
    {
        $initialText = 'Initial narration text for regeneration test.';
        $asset = $this->dispatchAndProcess($initialText);
        $assetId = (string) $asset->getId();

        $initialTts = $this->ttsAssetRepo->findByAsset($asset);
        self::assertNotNull($initialTts);
        self::assertSame(hash('sha256', $initialText), $initialTts->getSourceTextHash());

        $newText = 'Completely new narration text replacing the old one for this asset.';
        $regenDto = $this->buildSynthesizeDto($newText)->setRegenerateAssetId($assetId);

        $result = $this->dispatchFacade->synthesize($regenDto, enqueue: false);
        self::assertNotNull($result->narrationRequest, 'Regen dispatch must produce a request.');
        self::assertSame($assetId, $result->getAssetId(), 'Regen must reuse the stable asset id.');

        $this->drivePipeline((string) $result->narrationRequest->getId());

        $regenAsset = $this->assetRepo->find($assetId);
        self::assertInstanceOf(Asset::class, $regenAsset);

        $regenTts = $this->ttsAssetRepo->findByAsset($regenAsset);
        self::assertNotNull($regenTts);
        self::assertTrue($regenTts->getStatus()->is(TtsAudioStatus::Active), 'TtsAsset must be Active after regen.');
        self::assertSame($newText, $regenTts->getSourceTextSnapshot(), 'Snapshot must reflect new text.');
        self::assertSame(hash('sha256', $newText), $regenTts->getSourceTextHash(), 'Hash must match new text.');
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
     * Plans the request then drives the per-chunk chain until no pending chunks remain.
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
