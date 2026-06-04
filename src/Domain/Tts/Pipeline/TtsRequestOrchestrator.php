<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFileRoute\AssetFileRouteFacade;
use AnzuSystems\CoreDamBundle\Domain\AssetSlot\AssetSlotFactory;
use AnzuSystems\CoreDamBundle\Domain\Audio\AudioStatusFacade;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorProvider;
use AnzuSystems\CoreDamBundle\Domain\Keyword\KeywordProvider;
use AnzuSystems\CoreDamBundle\Domain\PodcastEpisode\PodcastEpisodeManager;
use AnzuSystems\CoreDamBundle\Domain\PodcastEpisode\PodcastLicenceFilter;
use AnzuSystems\CoreDamBundle\Domain\Tts\Catalog\VoiceResolver;
use AnzuSystems\CoreDamBundle\Domain\Tts\Config;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetLocker;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAudioFileRemover;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsChunkCleaner;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsNarrationRequestManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsSynthesisChunkManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Provider\TextChunker;
use AnzuSystems\CoreDamBundle\Domain\Tts\Provider\TtsProviderContainer;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\Keyword;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Entity\TtsSynthesisChunk;
use AnzuSystems\CoreDamBundle\Entity\Voice;
use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;
use AnzuSystems\CoreDamBundle\Event\Dispatcher\AssetChangedEventDispatcher;
use AnzuSystems\CoreDamBundle\Exception\RegenCancelledException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Messenger\Message\TtsSynthChunkMessage;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsAudioCreationInput;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsAudioCreationResult;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestMode;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Repository\KeywordRepository;
use AnzuSystems\CoreDamBundle\Repository\PodcastRepository;
use AnzuSystems\CoreDamBundle\Repository\TtsSynthesisChunkRepository;
use Closure;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

final readonly class TtsRequestOrchestrator
{
    // ElevenLabs previous_request_ids window — how many preceding chunks feed cross-splice prosody.
    private const int CHAIN_LIMIT = 3;

    public function __construct(
        private TtsNarrationRequestManager $requestManager,
        private AssetRepository $assetRepo,
        private TtsAssetLocker $ttsAssetLocker,
        private VoiceResolver $voiceResolver,
        private TtsProviderContainer $providerContainer,
        private TtsAudioFactory $ttsAudioFactory,
        private TtsAudioFileRemover $audioFileRemover,
        private AssetSlotFactory $assetSlotFactory,
        private Config $config,
        private AudioStatusFacade $audioStatusFacade,
        private PreviewMedia $previewMedia,
        private AssetSwap $assetSwap,
        private PodcastEpisodeManager $episodeManager,
        private PodcastRepository $podcastRepo,
        private PodcastLicenceFilter $podcastLicenceFilter,
        private AssetFileRouteFacade $routeFacade,
        private AssetChangedEventDispatcher $assetChangedEventDispatcher,
        private IndexManager $indexManager,
        private AssetManager $assetManager,
        private KeywordRepository $keywordRepo,
        private KeywordProvider $keywordProvider,
        private AuthorProvider $authorProvider,
        private TextChunker $textChunker,
        private TtsChunkStorage $chunkStorage,
        private TtsSynthesisChunkRepository $chunkRepo,
        private TtsSynthesisChunkManager $chunkManager,
        private TtsChunkCleaner $chunkCleaner,
        private MessageBusInterface $messageBus,
        private DamLogger $logger,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Plan step (runs after the request is claimed Processing): resolve voice, chunk the text, then either
     * synthesise inline (single chunk — the common case, one worker run like before) or persist one chunk
     * row per chunk and fan out per-chunk messages so each chunk gets its own run.
     */
    public function plan(TtsNarrationRequest $request): void
    {
        $extSystem = $request->getLicence()->getExtSystem();
        $voice = $this->voiceResolver->resolve($request->getVoiceFamilySlug(), $extSystem);
        $provider = $this->providerContainer->forDiscriminator($voice->getDiscriminator());
        // Re-validate tenant config right before synth (dispatch precheck may be stale).
        $provider->precheck($voice, $extSystem);

        $chunks = $this->textChunker->chunk(
            $this->resolveSourceText($request),
            max(1, min($this->config->getChunkSizeChars(), $provider->getMaxCharsPerRequest())),
        );
        if ([] === $chunks) {
            throw new RuntimeException(sprintf('TTS source text produced no chunks (request "%s").', (string) $request->getId()));
        }

        if (1 === count($chunks)) {
            $result = $provider->synthesizeChunk($chunks[0], $voice, $extSystem, []);
            $this->finalizeByMode($request, $this->chunkStorage->writeTmpMaster($result->bytes), $voice);

            return;
        }

        $this->createChunks($request, $chunks);
        $first = $this->chunkRepo->findNextPending((string) $request->getId());
        if (null !== $first) {
            $this->messageBus->dispatch(new TtsSynthChunkMessage((string) $first->getId()));
        }
    }

    /**
     * Synthesise one already-claimed chunk: provider call (outside any tx) → persist blob → mark Done →
     * dispatch the next chunk, or (last chunk) assemble + finalize inline. Throwing bubbles to the handler
     * which fails the whole request.
     */
    public function processChunk(TtsSynthesisChunk $chunk): void
    {
        $request = $chunk->getRequest();
        $requestId = (string) $request->getId();
        $extSystem = $request->getLicence()->getExtSystem();
        $voice = $this->voiceResolver->resolve($request->getVoiceFamilySlug(), $extSystem);
        $provider = $this->providerContainer->forDiscriminator($voice->getDiscriminator());

        $result = $provider->synthesizeChunk(
            $chunk->getSourceText(),
            $voice,
            $extSystem,
            $this->chunkRepo->findChainRequestIds($requestId, $chunk->getOrdinal(), self::CHAIN_LIMIT),
        );
        $path = $this->chunkStorage->write($extSystem, $requestId, $chunk->getOrdinal(), $result->bytes);

        $this->entityManager->wrapInTransaction(
            fn (): TtsSynthesisChunk => $this->chunkManager->markDone($chunk, $path, $result->requestId),
        );

        $next = $this->chunkRepo->findNextPending($requestId);
        if (null !== $next) {
            $this->messageBus->dispatch(new TtsSynthChunkMessage((string) $next->getId()));

            return;
        }

        // Last chunk — concat the Done blobs into the master and run the standard finalize, then purge chunks.
        $master = $this->chunkStorage->concatToMaster($extSystem, $this->chunkRepo->findAllDoneOrdered($requestId));
        $this->finalizeByMode($request, $master, $voice);
        $this->chunkCleaner->purge($request);
    }

    private function finalizeByMode(TtsNarrationRequest $request, AdapterFile $master, Voice $voice): void
    {
        match ($request->getMode()) {
            TtsRequestMode::Initial => $this->finalizeInitial($request, $master, $voice),
            TtsRequestMode::Regenerate => $this->finalizeRegenerate($request, $master, $voice),
        };
    }

    private function resolveSourceText(TtsNarrationRequest $request): string
    {
        return match ($request->getMode()) {
            TtsRequestMode::Initial => (string) $request->getSourceText(),
            TtsRequestMode::Regenerate => $this->ttsAssetLocker->requireFor($this->resolveTargetAsset($request))->getSourceTextSnapshot(),
        };
    }

    /**
     * @param list<string> $chunks
     */
    private function createChunks(TtsNarrationRequest $request, array $chunks): void
    {
        $this->entityManager->wrapInTransaction(function () use ($request, $chunks): void {
            foreach ($chunks as $ordinal => $text) {
                $this->chunkManager->create(
                    (new TtsSynthesisChunk())->setRequest($request)->setOrdinal($ordinal)->setSourceText($text),
                    flush: false,
                );
            }
            $this->entityManager->flush();
        });
    }

    private function finalizeInitial(TtsNarrationRequest $request, AdapterFile $master, Voice $voice): void
    {
        $licence = $request->getLicence();
        $extSystem = $licence->getExtSystem();
        // The file-less audio shell reserved at dispatch — its id is the one CMS already holds.
        $shellAsset = $this->resolveTargetAsset($request);
        $family = $voice->getVoiceFamily();
        $sourceText = (string) $request->getSourceText();

        $input = TtsAudioCreationInput::forInitialRequest($request, $master, $family, $voice, $licence, $sourceText);

        $result = $this->persistInTransaction($input, $shellAsset, static function (TtsAudioCreationResult $created): void {
            $created->asset->getAssetFlags()->setTtsAudio(true);
        });

        // Must be materialized + public before Done — a failure here is a real generation failure.
        $this->materializeMasterAudio($result->masterAudio, $result->masterTmpFile, dispatchPropertyRefresh: false);
        $this->routeFacade->makePublic($result->masterAudio, $result->masterRoute);

        // Published master = generation succeeded → commit Done now so the best-effort enrichment below
        // can't flip it to Failed or skip the CMS success callback.
        $this->requestManager->markDone($request);

        $orphanExpireAt = App::getAppDate()->modify(sprintf('+%d seconds', $this->config->getAudioRetentionGraceSeconds()));
        $this->bestEffort('processInitial.enrichmentFailed', $request, $result->asset, function () use ($result, $request, $extSystem, $family, $orphanExpireAt): void {
            $this->syncFamilyKeywords($result->asset, $family);
            $this->applyInitialMetadata($result->asset, $request, $extSystem);
            $this->syncPodcastMembership($request, $result->asset);

            $preview = $this->previewMedia->generate($result->masterAudio, $result->masterTmpFile, expireAt: $orphanExpireAt);
            $this->attachPreviewSlot($result->asset, $preview);
        });

        $this->bestEffort('processInitial.refreshIndexFailed', $request, $result->asset, function () use ($result): void {
            $this->assetManager->updateExisting($result->asset);
            $this->indexManager->index($result->asset);
        });

        $this->assetChangedEventDispatcher->dispatchAssetChangedEvent(new ArrayCollection([$result->asset]));
    }

    private function finalizeRegenerate(TtsNarrationRequest $request, AdapterFile $master, Voice $voice): void
    {
        $stableAsset = $this->resolveTargetAsset($request);
        $stableTts = $this->ttsAssetLocker->requireFor($stableAsset);
        $licence = $request->getLicence();
        $family = $voice->getVoiceFamily();

        $input = TtsAudioCreationInput::forRegenerate($request, $stableTts, $master, $family, $voice, $licence);

        // Safety expiry stamped on the new (unslotted) files so a crash before the swap leaves them reapable
        // by the grace cron; AssetSwap::promote() clears it when they go live.
        $orphanExpireAt = App::getAppDate()->modify(sprintf('+%d seconds', $this->config->getAudioRetentionGraceSeconds()));

        // Build + publish the new master/preview but DON'T slot them yet — the live asset keeps serving the
        // old audio until the atomic promote below, so a failure here leaves the old narration intact.
        $built = $this->entityManager->wrapInTransaction(
            fn (): TtsAudioCreationResult => $this->ttsAudioFactory->buildReplacementMaster($input, $stableAsset, $stableTts, $orphanExpireAt)
        );

        $newPreview = null;

        try {
            $this->materializeMasterAudio($built->masterAudio, $built->masterTmpFile, dispatchPropertyRefresh: false);
            $this->routeFacade->makePublic($built->masterAudio, $built->masterRoute);

            $newPreview = $this->previewMedia->generate($built->masterAudio, $built->masterTmpFile, expireAt: $orphanExpireAt);

            // Cancel check + atomic repoint to the new files; the old files are demoted with a grace expireAt.
            $this->assetSwap->promote(
                (string) $stableAsset->getId(),
                $built->masterAudio,
                $newPreview,
                (string) $request->getId(),
                $voice,
                $family,
            );
        } catch (Throwable $e) {
            $this->audioFileRemover->remove($built->masterAudio, $newPreview);

            throw $e;
        }

        // Swap is the point of no return → commit Done now so best-effort enrichment can't flip it to Failed.
        $this->requestManager->markDone($request);

        $this->bestEffort('processRegenerate.enrichmentFailed', $request, $stableAsset, function () use ($stableAsset, $family): void {
            $this->syncFamilyKeywords($stableAsset, $family);
            if ($this->ensureAutoKeyword($stableAsset, $stableAsset->getExtSystem())) {
                $this->entityManager->flush();
            }
            $this->indexManager->index($stableAsset);
        });

        $this->assetChangedEventDispatcher->dispatchAssetChangedEvent(new ArrayCollection([$stableAsset]));
    }

    /**
     * Attaches the preview onto the initial asset's empty preview slot (regen repoints via {@see AssetSwap::promote()}).
     */
    private function attachPreviewSlot(Asset $asset, AudioFile $preview): void
    {
        $this->entityManager->wrapInTransaction(function () use ($asset, $preview): void {
            $this->assetSlotFactory->replaceSlotFile($asset, $preview, $this->config->getPreviewSlotName());
            // Slotted (live) → clear the safety expireAt.
            $preview->setExpireAt(null);
            $this->entityManager->flush();
        });
    }

    private function syncPodcastMembership(TtsNarrationRequest $request, Asset $asset): void
    {
        if ([] === $request->getPodcastIds()) {
            $this->episodeManager->setMembership($asset, new ArrayCollection());

            return;
        }

        $desired = $this->podcastLicenceFilter->filter(
            $asset,
            $this->podcastRepo->findBy(['id' => $request->getPodcastIds()]),
        );

        $this->episodeManager->setMembership($asset, $desired);
    }

    /**
     * Adds the current family's keywords onto the asset (additive, idempotent). A later family change does
     * not prune already-applied keywords.
     */
    private function syncFamilyKeywords(Asset $asset, VoiceFamily $family): void
    {
        $changed = false;
        foreach ($family->getKeywords() as $keyword) {
            if ($asset->getKeywords()->contains($keyword)) {
                continue;
            }
            $asset->addKeyword($keyword);
            $changed = true;
        }

        if ($changed) {
            $this->entityManager->flush();
        }
    }

    /**
     * Links caller keyword/author names on initial generation (provide-or-create, de-duplicated).
     * The auto-keyword is separate ({@see ensureAutoKeyword}) so it survives regen.
     */
    private function applyInitialMetadata(Asset $asset, TtsNarrationRequest $request, ExtSystem $extSystem): void
    {
        $changed = $this->ensureAutoKeyword($asset, $extSystem);

        foreach (array_unique($request->getKeywords()) as $name) {
            $keyword = $this->keywordProvider->provideKeyword($name, $extSystem, flush: false);
            if ($keyword instanceof Keyword) {
                $asset->addKeyword($keyword);
                $changed = true;
            }
        }

        foreach (array_unique($request->getAuthors()) as $name) {
            $author = $this->authorProvider->provideByTitle($name, $extSystem);
            if ($author instanceof Author) {
                $asset->addAuthor($author);
                $changed = true;
            }
        }

        if ($changed) {
            $this->entityManager->flush();
            // Bulk-index the keyword/author entities here — the providers no longer index per-item.
            $indexEntities = array_values([...$asset->getAuthors(), ...$asset->getKeywords()]);
            if ([] !== $indexEntities) {
                $this->indexManager->indexBulk($indexEntities);
            }
        }
    }

    /**
     * Attaches the ext-system auto-keyword (in-memory; caller flushes). Idempotent; re-run on regen since
     * the family reconcile can drop a keyword equal to it.
     *
     * @return bool whether it was (re-)added
     */
    private function ensureAutoKeyword(Asset $asset, ExtSystem $extSystem): bool
    {
        $autoKeywordId = $extSystem->getTtsSettings()->getAutoKeywordId();
        if (null === $autoKeywordId || $asset->getKeywords()->containsKey($autoKeywordId)) {
            return false;
        }

        $autoKeyword = $this->keywordRepo->find($autoKeywordId);
        if (null === $autoKeyword) {
            return false;
        }

        $asset->addKeyword($autoKeyword);

        return true;
    }

    /**
     * Runs the synthesised audio through the standard pipeline ({@see AudioStatusFacade::storeAndProcess()}),
     * which swallows failures into a Failed status instead of throwing — so assert Processed and throw
     * otherwise, letting the worker handler mark the request Failed.
     */
    private function materializeMasterAudio(AudioFile $audioFile, AdapterFile $tmpFile, bool $dispatchPropertyRefresh): void
    {
        $this->audioStatusFacade->storeAndProcess(
            assetFile: $audioFile,
            file: $tmpFile,
            dispatchPropertyRefresh: $dispatchPropertyRefresh,
        );

        if ($audioFile->getAssetAttributes()->getStatus()->isNot(AssetFileProcessStatus::Processed)) {
            throw new RuntimeException(sprintf(
                'TTS master audio (%s) finished storeAndProcess in status "%s" instead of "%s".',
                (string) $audioFile->getId(),
                $audioFile->getAssetAttributes()->getStatus()->value,
                AssetFileProcessStatus::Processed->value,
            ));
        }
    }

    /**
     * Runs a post-commit step that must not undo the committed generation: on failure it logs and swallows,
     * so a flaky enrichment can't flip a succeeded request to Failed.
     */
    private function bestEffort(string $step, TtsNarrationRequest $request, Asset $asset, Closure $work): void
    {
        try {
            $work();
        } catch (Throwable $e) {
            $this->logger->error(DamLogger::NAMESPACE_TTS, $step, [
                'requestId' => (string) $request->getId(),
                'assetId' => (string) $asset->getId(),
            ], exception: $e);
        }
    }

    /**
     * @param null|Closure(TtsAudioCreationResult): void $afterCreate
     */
    private function persistInTransaction(TtsAudioCreationInput $input, Asset $asset, ?Closure $afterCreate = null): TtsAudioCreationResult
    {
        return $this->entityManager->wrapInTransaction(
            function () use ($input, $asset, $afterCreate): TtsAudioCreationResult {
                $created = $this->ttsAudioFactory->create($input, $asset);
                $afterCreate?->__invoke($created);
                $this->entityManager->flush();

                return $created;
            }
        );
    }

    private function resolveTargetAsset(TtsNarrationRequest $request): Asset
    {
        $assetId = (string) $request->getAssetId();
        $asset = $this->assetRepo->find($assetId);
        if (null === $asset) {
            throw new RegenCancelledException(
                sprintf('Target asset "%s" not found for request "%s".', $assetId, (string) $request->getId())
            );
        }

        return $asset;
    }
}
