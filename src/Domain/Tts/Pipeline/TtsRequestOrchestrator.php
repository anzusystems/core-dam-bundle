<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline;

use AnzuSystems\CoreDamBundle\Domain\AssetFileRoute\AssetFileRouteFacade;
use AnzuSystems\CoreDamBundle\Domain\Audio\AudioStatusFacade;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorProvider;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackFacade;
use AnzuSystems\CoreDamBundle\Domain\Keyword\KeywordProvider;
use AnzuSystems\CoreDamBundle\Domain\PodcastEpisode\PodcastEpisodeManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Catalog\VoiceResolver;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetLocker;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsNarrationRequestManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Provider\TtsProviderContainer;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\Keyword;
use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Entity\Voice;
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
        private AudioStatusFacade $audioStatusFacade,
        private PreviewMedia $previewMedia,
        private AssetSwap $assetSwap,
        private PodcastEpisodeManager $episodeManager,
        private PodcastRepository $podcastRepo,
        private AssetFileRouteFacade $routeFacade,
        private ExtSystemCallbackFacade $extSystemCallbackFacade,
        private IndexManager $indexManager,
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

        $voice = $this->voiceResolver->resolve($request->getVoiceFamilySlug(), $extSystem);
        $family = $voice->getVoiceFamily();
        $provider = $this->providerContainer->forDiscriminator($voice->getDiscriminator());

        $sourceText = (string) $request->getSource()->getText();
        $audioFile = $provider->synthesize($sourceText, $voice, $extSystem);

        $input = TtsAudioCreationInput::forInitialRequest($request, $audioFile, $family, $voice, $licence, $sourceText);

        $result = $this->persistInTransaction($input, static function (TtsAudioCreationResult $created): void {
            $created->asset->getAssetFileProperties()->setFromTts(true);
        });

        // Critical steps: the audio must materialize and be publicly routable for the narration to be
        // usable. A failure here is a genuine generation failure — the request stays non-terminal so the
        // handler marks it Failed and the ext-system drops its placeholder media.
        $this->materializeMasterAudio($result, dispatchPropertyRefresh: true);
        $this->routeFacade->makePublic($result->masterAudio, $result->masterRoute);

        // The asset is now playable — commit Done BEFORE the best-effort enrichment below so a failure
        // in keywords/metadata/index/preview/podcast can no longer flip the request to Failed (the
        // terminal-state guard + the handler's Done check both rely on this ordering).
        $this->requestManager->markDone($request, (string) $result->asset->getId());

        $this->syncFamilyKeywords($result->asset, $result->ttsAsset, $family);
        $this->applyInitialMetadata($result->asset, $request, $extSystem);
        $this->indexManager->index($result->asset);
        $this->previewMedia->generate($result->masterAudio, $result->masterTmpFile);
        $this->syncPodcastMembership($request, $result->asset);

        if (null !== $request->getExtRef()->getExtResourceName() && null !== $request->getExtRef()->getExtId()) {
            $this->extSystemCallbackFacade->notifyAssetsChanged(new ArrayCollection([$result->asset]));
        }
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

        $this->stageAndSwap($request, $stableAsset, $stableTts, $audioFile, $voice, $family, $licence);
    }

    private function stageAndSwap(
        TtsNarrationRequest $request,
        Asset $stableAsset,
        TtsAsset $stableTts,
        AdapterFile $audioFile,
        Voice $voice,
        VoiceFamily $family,
        AssetLicence $licence,
    ): void {
        $input = TtsAudioCreationInput::forStagingSwap($request, $stableTts, $audioFile, $family, $voice, $licence);

        $stagingResult = $this->persistInTransaction($input);

        // Staging bytes need to land in storage so AssetSwap can swap content into the stable file,
        // but no public route is published — the staging route is cascade-deleted with the staging asset.
        $this->materializeMasterAudio($stagingResult, dispatchPropertyRefresh: false);

        $this->previewMedia->generate($stagingResult->masterAudio, $stagingResult->masterTmpFile);

        $swapResult = $this->assetSwap->swap(
            (string) $stagingResult->asset->getId(),
            (string) $stableAsset->getId(),
            (string) $request->getId(),
        );

        $this->syncFamilyKeywords($stableAsset, $stableTts, $family);
        if ($this->ensureAutoKeyword($stableAsset, $stableAsset->getExtSystem())) {
            $this->entityManager->flush();
        }
        $this->indexManager->index($stableAsset);
        $this->requestManager->markDone($request, (string) $stableAsset->getId());

        $this->routeFacade->dispatchRoutePurgeForAssetFiles($swapResult->audioFilesToPurge);
        $this->extSystemCallbackFacade->notifyAssetsChanged(new ArrayCollection([$stableAsset]));
    }

    private function syncPodcastMembership(TtsNarrationRequest $request, Asset $asset): void
    {
        if ([] === $request->getPodcastIds()) {
            $this->episodeManager->setMembership($asset, new ArrayCollection());

            return;
        }

        $desired = new ArrayCollection();
        foreach ($this->podcastRepo->findBy(['id' => $request->getPodcastIds()]) as $podcast) {
            if ($podcast->getLicence()->is($asset->getLicence())) {
                $desired->add($podcast);

                continue;
            }

            $this->logger->warning(DamLogger::NAMESPACE_TTS, 'podcastMembership.licenceMismatch', [
                'podcastId' => (string) $podcast->getId(),
                'assetId' => (string) $asset->getId(),
                'podcastLicenceId' => (string) $podcast->getLicence()->getId(),
                'assetLicenceId' => (string) $asset->getLicence()->getId(),
            ]);
        }

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
        // Legacy single keyword unioned with the M:N set for back-compat.
        $legacyKeyword = $family->getKeyword();
        if (null !== $legacyKeyword) {
            $newKeywords[(string) $legacyKeyword->getId()] = $legacyKeyword;
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
     * Duplicate detection is skipped: TTS regenerations frequently produce byte-identical MP3s for the
     * same input, and would otherwise collapse onto a previous TTS asset.
     *
     * If the pipeline marks the file as Failed (status-facade swallows Throwables and transitions to
     * Failed instead of re-throwing), surface that here so the worker handler marks the request as failed.
     */
    private function materializeMasterAudio(TtsAudioCreationResult $result, bool $dispatchPropertyRefresh): void
    {
        $audioFile = $result->masterAudio;
        $this->audioStatusFacade->storeAndProcess(
            assetFile: $audioFile,
            file: $result->masterTmpFile,
            dispatchPropertyRefresh: $dispatchPropertyRefresh,
            skipDuplicateCheck: true,
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
    private function persistInTransaction(TtsAudioCreationInput $input, ?Closure $afterCreate = null): TtsAudioCreationResult
    {
        return $this->entityManager->wrapInTransaction(
            function () use ($input, $afterCreate): TtsAudioCreationResult {
                $created = $this->ttsAudioFactory->create($input);
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
