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
use AnzuSystems\CoreDamBundle\Domain\PodcastEpisode\PodcastEpisodeFactory;
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
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Messenger\Message\TtsNarrationRequestMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\TtsSynthChunkMessage;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsAudioCreationInput;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsAudioCreationResult;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsChunkStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestMode;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsResumeOutcome;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Repository\KeywordRepository;
use AnzuSystems\CoreDamBundle\Repository\PodcastRepository;
use AnzuSystems\CoreDamBundle\Repository\TtsSynthesisChunkRepository;
use Closure;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
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
        private PodcastEpisodeFactory $episodeFactory,
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
     * Resolve voice, chunk text, synthesise inline (single chunk) or fan-out per-chunk messages.
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
     * Provider call → persist blob → mark Done → dispatch next chunk, or assemble + finalize on last.
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

        // Last chunk — assemble + finalize (shared with reconcile sweep, idempotent).
        $this->finalizeRequest($request);
    }

    /**
     * Concat Done chunks into master and finalize; bails if request left Processing (idempotent).
     */
    public function finalizeRequest(TtsNarrationRequest $request): void
    {
        if ($request->getStatus()->isNot(TtsRequestStatus::Processing)) {
            return;
        }
        $requestId = (string) $request->getId();
        // Refuse to assemble while any chunk is still unfinished — a truncated master must not be published.
        $counts = $this->chunkRepo->progressCounts($requestId);
        if ($counts['total'] > 0 && $counts['done'] < $counts['total']) {
            throw new RuntimeException(sprintf(
                'Refusing to finalize request "%s": only %d of %d chunks are done.',
                $requestId,
                $counts['done'],
                $counts['total'],
            ));
        }
        $extSystem = $request->getLicence()->getExtSystem();
        $voice = $this->voiceResolver->resolve($request->getVoiceFamilySlug(), $extSystem);
        $master = $this->chunkStorage->concatToMaster($extSystem, $this->chunkRepo->findAllDoneOrdered($requestId));
        $this->finalizeByMode($request, $master, $voice);
        $this->chunkCleaner->purge($request);
    }

    /**
     * Reconcile a stalled Processing request; resets chunks whose worker died past the stale window.
     */
    public function resumeStalled(TtsNarrationRequest $request, DateTimeImmutable $processingStaleBefore): TtsResumeOutcome
    {
        if ($request->getStatus()->isNot(TtsRequestStatus::Processing)) {
            return TtsResumeOutcome::Skipped;
        }
        $requestId = (string) $request->getId();
        $chunks = $this->chunkRepo->findAllByRequest($requestId);
        if ($chunks->isEmpty()) {
            return $this->recoverChunkless($request);
        }
        foreach ($chunks as $chunk) {
            if ($chunk->getStatus()->is(TtsChunkStatus::Failed)) {
                return TtsResumeOutcome::HasFailed;
            }
        }
        $rearmed = false;
        foreach ($chunks as $chunk) {
            $startedAt = $chunk->getStartedAt();
            if ($chunk->getStatus()->is(TtsChunkStatus::Processing) && null !== $startedAt && $startedAt < $processingStaleBefore) {
                $this->chunkManager->markPending($chunk, flush: false);
                $rearmed = true;
            }
        }
        if ($rearmed) {
            $this->entityManager->flush();
        }
        $next = $this->chunkRepo->findNextPending($requestId);
        if (null !== $next) {
            $this->messageBus->dispatch(new TtsSynthChunkMessage((string) $next->getId()));

            return TtsResumeOutcome::Redispatched;
        }
        $this->finalizeRequest($request);

        return TtsResumeOutcome::Finalized;
    }

    /**
     * Re-plan a chunkless Processing request (worker died before persisting) only if the asset has no audio yet.
     */
    private function recoverChunkless(TtsNarrationRequest $request): TtsResumeOutcome
    {
        $asset = $this->assetRepo->find((string) $request->getAssetId());
        if (null === $asset || $this->assetHasAudio($asset)) {
            return TtsResumeOutcome::NoChunks;
        }

        $this->requestManager->markWaiting($request);
        $this->messageBus->dispatch(new TtsNarrationRequestMessage((string) $request->getId()));

        return TtsResumeOutcome::Redispatched;
    }

    private function assetHasAudio(Asset $asset): bool
    {
        foreach ($asset->getSlots() as $slot) {
            if (null !== $slot->getAudio()) {
                return true;
            }
        }

        return false;
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
        // Shell asset reserved at dispatch — its id is the one CMS already holds.
        $shellAsset = $this->resolveTargetAsset($request);
        $family = $voice->getVoiceFamily();
        $sourceText = (string) $request->getSourceText();

        $input = TtsAudioCreationInput::forInitialRequest($request, $master, $family, $voice, $licence, $sourceText);

        $result = $this->persistInTransaction($input, $shellAsset, static function (TtsAudioCreationResult $created): void {
            $created->asset->getAssetFlags()->setTtsAudio(true);
        });

        // Must be materialized + public before Done — failure here is a real generation failure.
        $this->materializeMasterAudio($result->masterAudio, $result->masterTmpFile, dispatchPropertyRefresh: false);
        $this->routeFacade->makePublic($result->masterAudio, $result->masterRoute);

        // Commit Done now so best-effort enrichment below can't flip it to Failed.
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

        // Safety expiry on unslotted files; AssetSwap::promote() clears it when they go live.
        $orphanExpireAt = App::getAppDate()->modify(sprintf('+%d seconds', $this->config->getAudioRetentionGraceSeconds()));

        // Build + publish new master/preview without slotting — failure leaves old narration intact.
        $built = $this->entityManager->wrapInTransaction(
            fn (): TtsAudioCreationResult => $this->ttsAudioFactory->buildReplacementMaster($input, $stableAsset, $stableTts, $orphanExpireAt)
        );

        $newPreview = null;

        try {
            $this->materializeMasterAudio($built->masterAudio, $built->masterTmpFile, dispatchPropertyRefresh: false);
            $this->routeFacade->makePublic($built->masterAudio, $built->masterRoute);

            $newPreview = $this->previewMedia->generate($built->masterAudio, $built->masterTmpFile, expireAt: $orphanExpireAt);

            // Atomic repoint; old files demoted with grace expireAt.
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

        // Swap is point of no return — commit Done so best-effort enrichment can't flip it to Failed.
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
     * Attaches preview onto the initial asset's preview slot (regen repoints via AssetSwap::promote()).
     */
    private function attachPreviewSlot(Asset $asset, AudioFile $preview): void
    {
        $this->entityManager->wrapInTransaction(function () use ($asset, $preview): void {
            $this->assetSlotFactory->replaceSlotFile($asset, $preview, $this->config->getPreviewSlotName());
            // Now live on slot — clear the safety expireAt.
            $preview->setExpireAt(null);
            $this->entityManager->flush();
        });
    }

    private function syncPodcastMembership(TtsNarrationRequest $request, Asset $asset): void
    {
        if ([] === $request->getPodcastIds()) {
            $this->episodeFactory->setMembership($asset, new ArrayCollection());

            return;
        }

        $desired = $this->podcastLicenceFilter->filter(
            $asset,
            $this->podcastRepo->findBy(['id' => $request->getPodcastIds()]),
        );

        $this->episodeFactory->setMembership($asset, $desired, inheritFromAsset: true);
    }

    /**
     * Adds the family's keywords to the asset (additive, idempotent; prior keywords are not pruned).
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
            $author = $this->authorProvider->provideByTitle($name, $extSystem, flush: false);
            if ($author instanceof Author) {
                $asset->addAuthor($author);
                $changed = true;
            }
        }

        if ($changed) {
            $this->entityManager->flush();
            // Bulk-index keyword/author entities — providers no longer index per-item.
            $indexEntities = array_values([...$asset->getAuthors(), ...$asset->getKeywords()]);
            if ([] !== $indexEntities) {
                $this->indexManager->indexBulk($indexEntities);
            }
        }
    }

    /**
     * Attaches the ext-system auto-keyword in-memory; caller flushes. Re-run on regen (family reconcile can drop it).
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
     * storeAndProcess swallows failures into Failed status — assert Processed and throw otherwise.
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
     * Logs and swallows failures so a flaky enrichment step can't flip a succeeded request to Failed.
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
