<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Functional\Tts;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Tts\Config;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsNarrationRequestManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsSynthesisChunkManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Entity\TtsSynthesisChunk;
use AnzuSystems\CoreDamBundle\Exception\TtsProviderException;
use AnzuSystems\CoreDamBundle\Messenger\Handler\TtsNarrationRequestHandler;
use AnzuSystems\CoreDamBundle\Messenger\Handler\TtsSynthChunkHandler;
use AnzuSystems\CoreDamBundle\Messenger\Message\TtsNarrationRequestMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\TtsSynthChunkMessage;
use AnzuSystems\CoreDamBundle\Model\Enum\DispatchStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsAudioStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Repository\TtsAssetRepository;
use AnzuSystems\CoreDamBundle\Repository\TtsNarrationRequestRepository;
use AnzuSystems\CoreDamBundle\Repository\TtsSynthesisChunkRepository;
use AnzuSystems\CoreDamBundle\Tests\HttpClient\ElevenlabsClientMock;
use Throwable;

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
     * Permanent provider error (4xx) → request marked Failed, reserved asset dropped.
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

    /**
     * Second dispatch of content that is still in flight → AlreadyPending carrying the in-flight
     * asset id (the caller attaches it and waits for the completion callback).
     */
    public function testSecondDispatchOfInFlightContentReturnsAlreadyPendingWithAssetId(): void
    {
        $text = 'Identical narration text dispatched twice while the first is still in flight.';
        $first = $this->dispatchFacade->synthesize($this->buildSynthesizeDto($text), enqueue: false);
        self::assertNotNull($first->narrationRequest, 'First dispatch should produce a request.');

        $second = $this->dispatchFacade->synthesize($this->buildSynthesizeDto($text), enqueue: false);
        self::assertTrue(
            $second->status->is(DispatchStatus::AlreadyPending),
            'Second dispatch of in-flight content must report AlreadyPending.',
        );
        self::assertSame(
            $first->getAssetId(),
            $second->getAssetId(),
            'AlreadyPending must carry the in-flight asset id.',
        );
        self::assertNull($second->narrationRequest, 'AlreadyPending must not create a second request.');
    }

    /**
     * Transient provider error on a fresh request (sync transport drives the chunk inline) → claim
     * released back to Waiting, rethrown for redelivery, reserved asset kept, chunk re-armed.
     * A second delivery must reuse the existing chunk instead of creating duplicates.
     */
    public function testTransientProviderErrorReleasesClaimForRedelivery(): void
    {
        $result = $this->dispatchFacade->synthesize(
            $this->buildSynthesizeDto(ElevenlabsClientMock::FORCE_TRANSIENT_FAIL_MARKER . ' a narration that hits an outage.'),
            enqueue: false,
        );
        self::assertNotNull($result->narrationRequest, 'Initial dispatch should produce a request.');
        $requestId = (string) $result->narrationRequest->getId();
        $reservedAssetId = (string) $result->getAssetId();

        $thrown = $this->deliverExpectingTransient($requestId);
        self::assertTrue($thrown->isTransient());

        $request = $this->requestRepo->find($requestId);
        self::assertInstanceOf(TtsNarrationRequest::class, $request);
        self::assertTrue(
            $request->getStatus()->is(TtsRequestStatus::Waiting),
            'A transient failure must release the claim back to Waiting.',
        );
        self::assertInstanceOf(
            Asset::class,
            $this->assetRepo->find($reservedAssetId),
            'The reserved asset must survive a transient failure.',
        );

        $chunks = $this->chunkRepo->findAllByRequest($requestId);
        self::assertCount(1, $chunks, 'The chunk must persist for a billing-free retry.');

        $this->deliverExpectingTransient($requestId);
        self::assertCount(
            1,
            $this->chunkRepo->findAllByRequest($requestId),
            'Redelivery must reuse the existing chunk, never duplicate it.',
        );
    }

    /**
     * Transient provider error on a chunk → chunk re-armed to Pending, request stays Processing,
     * exception rethrown for transport redelivery. The chunk is seeded directly — the sync test
     * transport would otherwise drive the whole chunk chain inline within plan().
     */
    public function testTransientChunkErrorRearmsChunkForRetry(): void
    {
        $request = $this->dispatchWaitingRequest('Chunked narration hitting a transient provider failure.');
        $requestId = (string) $request->getId();

        $this->getService(TtsNarrationRequestManager::class)->markProcessing($request);
        $chunk = $this->getService(TtsSynthesisChunkManager::class)->create(
            (new TtsSynthesisChunk())
                ->setRequest($request)
                ->setOrdinal(App::ZERO)
                ->setSourceText(ElevenlabsClientMock::FORCE_TRANSIENT_FAIL_MARKER . ' zlyhajúca veta.'),
        );
        $chunkId = (string) $chunk->getId();

        $thrown = null;

        try {
            ($this->chunkHandler)(new TtsSynthChunkMessage($chunkId));
        } catch (TtsProviderException $e) {
            $thrown = $e;
        }

        self::assertNotNull($thrown, 'A transient chunk error must be rethrown for transport redelivery.');
        self::assertTrue($thrown->isTransient());

        $reloaded = $this->requestRepo->find($requestId);
        self::assertInstanceOf(TtsNarrationRequest::class, $reloaded);
        self::assertTrue(
            $reloaded->getStatus()->is(TtsRequestStatus::Processing),
            'The request must stay Processing across a transient chunk failure.',
        );

        $rearmed = $this->chunkRepo->findNextPending($requestId);
        self::assertNotNull($rearmed, 'A transient chunk failure must re-arm the chunk to Pending.');
        self::assertSame($chunkId, (string) $rearmed->getId());
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
        $mainImageFileId = '018e0000-0000-7000-8000-000000000001';
        $initialText = 'Initial narration text for regeneration test.';
        $asset = $this->dispatchAndProcess($initialText, $mainImageFileId);
        $assetId = (string) $asset->getId();

        $initialTts = $this->ttsAssetRepo->findByAsset($asset);
        self::assertNotNull($initialTts);
        self::assertSame(hash('sha256', $initialText), $initialTts->getSourceTextHash());
        self::assertSame($mainImageFileId, $initialTts->getMainImageFileId());

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
        self::assertSame(
            $mainImageFileId,
            $regenTts->getMainImageFileId(),
            'Regen without mainImageFileId in the request must keep the previous value.',
        );
    }

    private function deliverExpectingTransient(string $requestId): TtsProviderException
    {
        try {
            ($this->planHandler)(new TtsNarrationRequestMessage($requestId));
        } catch (Throwable $e) {
            // The sync test transport wraps the chunk handler's throw; production delivers it bare.
            $root = TtsProviderException::findTransient($e);
            self::assertInstanceOf(TtsProviderException::class, $root);

            return $root;
        }

        self::fail('A transient provider error must be rethrown for transport redelivery.');
    }

    private function dispatchAndProcess(string $text, ?string $mainImageFileId = null): Asset
    {
        $dto = $this->buildSynthesizeDto($text)->setMainImageFileId($mainImageFileId);
        $result = $this->dispatchFacade->synthesize($dto, enqueue: false);
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
