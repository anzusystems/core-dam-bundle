<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline;

use AnzuSystems\CoreDamBundle\Domain\Asset\AssetManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFileRoute\AssetFileRouteFacade;
use AnzuSystems\CoreDamBundle\Domain\AssetSlot\AssetSlotFactory;
use AnzuSystems\CoreDamBundle\Domain\Audio\AudioStatusFacade;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorProvider;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackFacade;
use AnzuSystems\CoreDamBundle\Domain\Keyword\KeywordProvider;
use AnzuSystems\CoreDamBundle\Domain\PodcastEpisode\PodcastEpisodeManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Catalog\VoiceResolver;
use AnzuSystems\CoreDamBundle\Domain\Tts\Config;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetLocker;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAudioFileRemover;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsNarrationRequestManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\PodcastLicenceFilter;
use AnzuSystems\CoreDamBundle\Domain\Tts\Provider\TtsProviderContainer;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\Keyword;
use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;
use AnzuSystems\CoreDamBundle\Exception\RegenCancelledException;
use AnzuSystems\CoreDamBundle\Exception\TtsProviderException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsAudioCreationInput;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsAudioCreationResult;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Repository\KeywordRepository;
use AnzuSystems\CoreDamBundle\Repository\PodcastRepository;
use Closure;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Throwable;

final readonly class TtsRequestOrchestrator
{
    public function __construct(
        private TtsNarrationRequestManager $requestManager,
        private AssetRepository $assetRepo,
        private TtsAssetLocker $ttsAssetLocker,
        private AssetLicenceRepository $licenceRepo,
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
        private ExtSystemCallbackFacade $extSystemCallbackFacade,
        private IndexManager $indexManager,
        private AssetManager $assetManager,
        private KeywordRepository $keywordRepo,
        private KeywordProvider $keywordProvider,
        private AuthorProvider $authorProvider,
        private DamLogger $logger,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function processInitial(TtsNarrationRequest $request): void
    {
        $licence = $this->resolveAssetLicence($request);
        $extSystem = $licence->getExtSystem();
        // The file-less audio shell reserved at dispatch — its id is the one CMS already holds.
        $shellAsset = $this->resolveStableAsset($request);

        $voice = $this->voiceResolver->resolve($request->getVoiceFamilySlug(), $extSystem);
        $family = $voice->getVoiceFamily();
        $provider = $this->providerContainer->forDiscriminator($voice->getDiscriminator());

        $sourceText = (string) $request->getSource()->getText();
        $audioFile = $provider->synthesize($sourceText, $voice, $extSystem);

        $input = TtsAudioCreationInput::forInitialRequest($request, $audioFile, $family, $voice, $licence, $sourceText);

        $result = $this->persistInTransaction($input, $shellAsset, static function (TtsAudioCreationResult $created): void {
            // Mark the asset as TTS-generated audio (manually toggleable later in the sidebar).
            $created->asset->getAssetFlags()->setTtsAudio(true);
        });

        // Audio must materialize and be publicly routable before we mark Done; a failure here is a real
        // generation failure (request stays non-terminal → marked Failed, shell asset dropped). Property
        // refresh is intentionally NOT dispatched here — the synchronous updateExisting() below owns it once
        // both slots (master + preview) exist, so slotNames & co. reflect the final state.
        $this->materializeMasterAudio($result->masterAudio, $result->masterTmpFile, dispatchPropertyRefresh: false);
        $this->routeFacade->makePublic($result->masterAudio, $result->masterRoute);

        // Generation succeeded once the master is published — commit Done now so the best-effort enrichment
        // below can't flip it to Failed, and (crucially) so the CMS success callback is still sent even if a
        // cosmetic enrichment step (e.g. ffmpeg preview) fails. DB enrichment runs before the flaky ffmpeg
        // preview so the callback reflects keywords/podcast even when the preview fails.
        $this->requestManager->markDone($request, (string) $result->asset->getId());

        // Best-effort enrichment. The preview is flaky ffmpeg; isolating it (and the metadata steps) here means
        // a failure can no longer skip the property refresh + reindex below.
        try {
            $this->syncFamilyKeywords($result->asset, $result->ttsAsset, $family);
            $this->applyInitialMetadata($result->asset, $request, $extSystem);
            $this->syncPodcastMembership($request, $result->asset);

            $preview = $this->previewMedia->generate($result->masterAudio, $result->masterTmpFile);
            $this->attachPreviewSlot($result->asset, $preview);
        } catch (Throwable $e) {
            $this->logger->error(DamLogger::NAMESPACE_TTS, 'processInitial.enrichmentFailed', [
                'requestId' => (string) $request->getId(),
                'assetId' => (string) $result->asset->getId(),
            ], exception: $e);
        }

        // Single point of truth for derived properties (slotNames, status, …) now that both slots + metadata
        // exist, then (re)index so ES reflects keywords/authors/podcast + slotNames. Guaranteed regardless of
        // whether the best-effort enrichment above (e.g. the ffmpeg preview) failed.
        try {
            $this->assetManager->updateExisting($result->asset);
            $this->indexManager->index($result->asset);
        } catch (Throwable $e) {
            $this->logger->error(DamLogger::NAMESPACE_TTS, 'processInitial.refreshIndexFailed', [
                'requestId' => (string) $request->getId(),
                'assetId' => (string) $result->asset->getId(),
            ], exception: $e);
        }

        $this->extSystemCallbackFacade->notifyAssetsChanged(new ArrayCollection([$result->asset]));
    }

    public function processRegenerate(TtsNarrationRequest $request): void
    {
        $stableAsset = $this->resolveStableAsset($request);
        $stableTts = $this->ttsAssetLocker->requireFor($stableAsset);
        $licence = $this->resolveAssetLicence($request);
        $voice = $this->voiceResolver->resolve($request->getVoiceFamilySlug(), $stableAsset->getExtSystem());
        $family = $voice->getVoiceFamily();

        $audioFile = $this->providerContainer->forDiscriminator($voice->getDiscriminator())
            ->synthesize($stableTts->getSourceTextSnapshot(), $voice, $stableAsset->getExtSystem());

        $input = TtsAudioCreationInput::forRegenerate($request, $stableTts, $audioFile, $family, $voice, $licence);

        // Build the new master + preview on the stable asset but NOT yet slotted, and fully materialize +
        // publish their bytes to fresh public-bucket paths (= fresh CDN URLs). The live asset still serves the
        // old audio until the atomic promote below — so a failure here leaves the old narration fully intact.
        $built = $this->entityManager->wrapInTransaction(
            fn (): TtsAudioCreationResult => $this->ttsAudioFactory->buildReplacementMaster($input, $stableAsset, $stableTts)
        );

        // The new master/preview are built + published BEFORE the swap, so until they are slotted by promote()
        // they are unreferenced orphans (no slot, no expireAt → the reaper never collects them). If the swap
        // aborts (concurrent cancel / wrong status) or any pre-swap step fails, delete them explicitly.
        $newPreview = null;

        try {
            $this->materializeMasterAudio($built->masterAudio, $built->masterTmpFile, dispatchPropertyRefresh: false);
            $this->routeFacade->makePublic($built->masterAudio, $built->masterRoute);

            $newPreview = $this->previewMedia->generate($built->masterAudio, $built->masterTmpFile);

            // Cooperative cancel check + atomic repoint: master/preview slots swing to the new files, the old
            // files are demoted with a grace-period expireAt (kept streamable), the TtsAsset is reactivated.
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

        // Swap is the point of no return — commit Done immediately so a failure in the best-effort enrichment
        // below can't (a) flip the request to Failed and fire a spurious failure callback after a live swap, or
        // (b) skip the CMS success callback.
        $this->requestManager->markDone($request, (string) $stableAsset->getId());

        try {
            $this->syncFamilyKeywords($stableAsset, $stableTts, $family);
            if ($this->ensureAutoKeyword($stableAsset, $stableAsset->getExtSystem())) {
                $this->entityManager->flush();
            }
            $this->indexManager->index($stableAsset);
        } catch (Throwable $e) {
            $this->logger->error(DamLogger::NAMESPACE_TTS, 'processRegenerate.enrichmentFailed', [
                'requestId' => (string) $request->getId(),
                'assetId' => (string) $stableAsset->getId(),
            ], exception: $e);
        }

        $this->extSystemCallbackFacade->notifyAssetsChanged(new ArrayCollection([$stableAsset]));
    }

    /**
     * Attaches the freshly-built+published preview onto the (empty) preview slot of the initial-generation asset.
     * Regeneration repoints the preview slot via {@see AssetSwap::promote()} instead. Runs in its own short transaction.
     */
    private function attachPreviewSlot(Asset $asset, AudioFile $preview): void
    {
        $this->entityManager->wrapInTransaction(function () use ($asset, $preview): void {
            $this->assetSlotFactory->replaceSlotFile($asset, $preview, $this->config->getPreviewSlotName());
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
     * Reconciles the family keyword set onto the asset without touching keywords from other sources.
     * Runs on initial + regen — the family can change between regens.
     */
    private function syncFamilyKeywords(Asset $asset, TtsAsset $ttsAsset, VoiceFamily $family): void
    {
        $newKeywords = [];
        foreach ($family->getKeywords() as $keyword) {
            $newKeywords[(string) $keyword->getId()] = $keyword;
        }

        $oldIds = $ttsAsset->getVoiceFamilyKeywordIds();
        $newIds = array_keys($newKeywords);

        $toRemove = array_diff($oldIds, $newIds);
        $toAdd = array_diff($newIds, $oldIds);
        if ([] === $toRemove && [] === $toAdd) {
            return;
        }

        foreach ($toRemove as $removedId) {
            $asset->removeKeywordById($removedId);
        }
        foreach ($toAdd as $addedId) {
            $asset->addKeyword($newKeywords[$addedId]);
        }

        $ttsAsset->setVoiceFamilyKeywordIds($newIds);
        $this->entityManager->flush();
    }

    /**
     * Links caller keyword/author names to the asset on initial generation, creating any that don't yet
     * exist in the ext-system (same provide-or-create services as the sys asset-file/from-url flow).
     * Names are de-duplicated first. The auto-keyword is separate ({@see ensureAutoKeyword}) so it survives regen.
     */
    private function applyInitialMetadata(Asset $asset, TtsNarrationRequest $request, ExtSystem $extSystem): void
    {
        $changed = $this->ensureAutoKeyword($asset, $extSystem);

        foreach (array_unique($request->getKeywords()) as $name) {
            $keyword = $this->keywordProvider->provideKeyword($name, $extSystem, false);
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
        }
    }

    /**
     * Attaches the ext-system auto-keyword (in-memory; caller flushes). Re-run on regen because the
     * family reconcile can drop a keyword that equals it. Idempotent.
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
     * Push the synthesised MP3 bytes through the standard audio pipeline:
     * {@see AudioStatusFacade::storeAndProcess()} writes to final storage, extracts duration/codec/metadata,
     * transitions the AudioFile to {@see AssetFileProcessStatus::Processed} (also flipping Asset → WithFile),
     * dispatches AssetFileChangedEvent and the AssetRefreshProperties message.
     *
     * Duplicate detection runs (licence checksum invariant), but a regen producing byte-identical audio
     * to the asset's own current master is not a duplicate — {@see AudioStatusFacade::checkDuplicate()}
     * excludes the same asset. Cross-article identical text is already short-circuited earlier at dispatch
     * ({@see \AnzuSystems\CoreDamBundle\Domain\Tts\Facade\TtsDispatchFacade}, PRVÝ BERIE).
     *
     * If the pipeline marks the file as Failed (status-facade swallows Throwables and transitions to
     * Failed instead of re-throwing), surface that here so the worker handler marks the request as failed.
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

    private function resolveStableAsset(TtsNarrationRequest $request): Asset
    {
        $stableAssetId = (string) $request->getStableAssetId();
        $stableAsset = $this->assetRepo->find($stableAssetId);
        if (null === $stableAsset) {
            throw new RegenCancelledException(
                sprintf('Stable asset "%s" not found for request "%s".', $stableAssetId, (string) $request->getId())
            );
        }

        return $stableAsset;
    }

    /**
     * @throws TtsProviderException if licence is not found
     */
    private function resolveAssetLicence(TtsNarrationRequest $request): AssetLicence
    {
        $licenceId = $request->getAssetLicenceId();
        if (null === $licenceId) {
            throw new TtsProviderException(sprintf('Request "%s" has no assetLicenceId.', (string) $request->getId()));
        }

        $licence = $this->licenceRepo->find($licenceId);
        if (null === $licence) {
            throw new TtsProviderException(sprintf('AssetLicence "%s" not found for request "%s".', $licenceId, (string) $request->getId()));
        }

        return $licence;
    }

}
