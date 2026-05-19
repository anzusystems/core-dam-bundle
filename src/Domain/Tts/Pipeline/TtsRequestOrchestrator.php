<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline;

use AnzuSystems\CoreDamBundle\Domain\AssetFileRoute\AssetFileRouteFacade;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackFacade;
use AnzuSystems\CoreDamBundle\Domain\Tts\Catalog\VoiceResolver;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetLocker;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsNarrationRequestManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Provider\TtsProviderContainer;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;
use AnzuSystems\CoreDamBundle\Exception\RegenCancelledException;
use AnzuSystems\CoreDamBundle\Exception\TtsProviderException;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsAudioCreationInput;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsAudioCreationResult;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Voice\ResolvedVoice;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsAudioStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Repository\VoiceFamilyRepository;
use Closure;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;

final readonly class TtsRequestOrchestrator
{
    public function __construct(
        private TtsNarrationRequestManager $requestManager,
        private AssetRepository $assetRepo,
        private TtsAssetLocker $ttsAssetLocker,
        private AssetLicenceRepository $licenceRepo,
        private VoiceFamilyRepository $voiceFamilyRepo,
        private VoiceResolver $voiceResolver,
        private TtsProviderContainer $providerContainer,
        private TtsAudioFactory $ttsAudioFactory,
        private PreviewMedia $previewMedia,
        private AssetSwap $assetSwap,
        private PodcastMembership $podcastMembership,
        private AssetFileRouteFacade $routeFacade,
        private ExtSystemCallbackFacade $extSystemCallbackFacade,
        private IndexManager $indexManager,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function processInitial(TtsNarrationRequest $request): void
    {
        $licence = $this->resolveAssetLicence($request);
        $extSystem = $licence->getExtSystem();

        $resolvedVoice = $this->voiceResolver->resolve($request->getVoiceFamilySlug(), $extSystem);
        $family = $this->resolveVoiceFamily($resolvedVoice->voiceFamilyId);
        $provider = $this->providerContainer->forProvider($resolvedVoice->provider);

        $sourceText = (string) $request->getSource()->getText();
        $audioFile = $provider->synthesize($sourceText, $resolvedVoice->externalVoiceId, $extSystem);

        $input = TtsAudioCreationInput::forInitialRequest($request, $audioFile, $family, $resolvedVoice, $licence, $sourceText);

        $result = $this->persistInTransaction($input, function (TtsAudioCreationResult $created) use ($request): void {
            $this->requestManager->markDone($request, (string) $created->asset->getId(), false);
        });

        $this->syncFamilyKeyword($result->asset, $result->ttsAsset, $family);
        $this->indexManager->index($result->asset);
        $this->previewMedia->generate($result->masterAudio, $result->masterTmpFile);
        $this->syncPodcastMembershipIfEligible($result->ttsAsset, $result->asset);

        if (null !== $request->getExtRef()->getExtResourceName() && null !== $request->getExtRef()->getExtId()) {
            $this->extSystemCallbackFacade->notifyAssetsChanged(new ArrayCollection([$result->asset]));
        }
    }

    public function processRegenerate(TtsNarrationRequest $request): void
    {
        $stableAsset = $this->resolveStableAsset($request);
        $stableTts = $this->ttsAssetLocker->requireFor($stableAsset);
        $licence = $this->resolveAssetLicence($request);
        $resolvedVoice = $this->voiceResolver->resolve($request->getVoiceFamilySlug(), $stableAsset->getExtSystem());
        $family = $this->resolveVoiceFamily($resolvedVoice->voiceFamilyId);

        $audioFile = $this->providerContainer->forProvider($resolvedVoice->provider)
            ->synthesize($stableTts->getSourceTextSnapshot(), $resolvedVoice->externalVoiceId, $stableAsset->getExtSystem());

        $this->stageAndSwap($request, $stableAsset, $stableTts, $audioFile, $resolvedVoice, $family, $licence);
    }

    private function stageAndSwap(
        TtsNarrationRequest $request,
        Asset $stableAsset,
        TtsAsset $stableTts,
        AdapterFile $audioFile,
        ResolvedVoice $resolvedVoice,
        VoiceFamily $family,
        AssetLicence $licence,
    ): void {
        $input = TtsAudioCreationInput::forStagingSwap($request, $stableTts, $audioFile, $family, $resolvedVoice, $licence);

        $stagingResult = $this->persistInTransaction($input);

        $this->previewMedia->generate($stagingResult->masterAudio, $stagingResult->masterTmpFile);

        $swapResult = $this->assetSwap->swap(
            (string) $stagingResult->asset->getId(),
            (string) $stableAsset->getId(),
            (string) $request->getId(),
        );

        $this->syncFamilyKeyword($stableAsset, $stableTts, $family);
        $this->indexManager->index($stableAsset);
        $this->requestManager->markDone($request, (string) $stableAsset->getId());

        $this->routeFacade->dispatchRoutePurgeForAssetFiles($swapResult->audioFilesToPurge);
        $this->extSystemCallbackFacade->notifyAssetsChanged(new ArrayCollection([$stableAsset]));
    }

    private function syncPodcastMembershipIfEligible(TtsAsset $ttsAsset, Asset $asset): void
    {
        $autoPodcastId = $ttsAsset->getAutoPodcastId();
        if ($ttsAsset->getStatus()->isNot(TtsAudioStatus::Active) || $ttsAsset->isStaging() || null === $autoPodcastId) {
            return;
        }

        $this->podcastMembership->syncAutoPodcast($asset, $autoPodcastId);
        if ($ttsAsset->isIncludeInRecommendedPodcast()) {
            $this->podcastMembership->syncRecommendedPodcast($asset, $ttsAsset->getRecommendedPodcastId(), true);
        }
    }

    private function syncFamilyKeyword(Asset $asset, TtsAsset $ttsAsset, VoiceFamily $family): void
    {
        $oldKeywordId = $ttsAsset->getVoiceFamilyKeywordId();
        $newKeyword = $family->getKeyword();
        $newKeywordId = null === $newKeyword ? null : (string) $newKeyword->getId();

        if ($oldKeywordId === $newKeywordId) {
            return;
        }

        if (null !== $oldKeywordId) {
            $asset->removeKeywordById($oldKeywordId);
        }

        if (null !== $newKeyword) {
            $asset->addKeyword($newKeyword);
        }

        $ttsAsset->setVoiceFamilyKeywordId($newKeywordId);
        $this->entityManager->flush();
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

    /**
     * @throws TtsProviderException if the resolver returned a stale ID
     */
    private function resolveVoiceFamily(string $voiceFamilyId): VoiceFamily
    {
        $family = $this->voiceFamilyRepo->find($voiceFamilyId);
        if (null === $family) {
            throw new TtsProviderException(sprintf('VoiceFamily "%s" not found.', $voiceFamilyId));
        }

        return $family;
    }
}
