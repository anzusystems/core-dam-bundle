<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline;

use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetLocker;

use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\JobAudioNarrationManager;

use AnzuSystems\CoreDamBundle\Domain\Tts\Catalog\VoiceResolver;

use AnzuSystems\CoreDamBundle\Domain\AssetFileRoute\AssetFileRouteFacade;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackFacade;
use AnzuSystems\CoreDamBundle\Domain\Tts\Provider\TtsProviderContainer;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\JobAudioNarration;
use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
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

/**
 * Owns the two TTS job pipelines (initial synth + regenerate). The Messenger handler hands a job
 * over here and stays thin (dispatch + failure logging only).
 */
final readonly class TtsJobOrchestrator
{
    public function __construct(
        private JobAudioNarrationManager $jobManager,
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
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function processInitial(JobAudioNarration $job): void
    {
        $licence = $this->resolveAssetLicence($job);
        $extSystem = $licence->getExtSystem();

        $resolvedVoice = $this->voiceResolver->resolve($job->getVoiceFamilySlug(), $extSystem);
        $family = $this->resolveVoiceFamily($resolvedVoice->voiceFamilyId);
        $provider = $this->providerContainer->forProvider($resolvedVoice->provider);

        $sourceText = (string) $job->getSource()->getText();
        $audioFile = $provider->synthesize($sourceText, $resolvedVoice->externalVoiceId, $extSystem);

        $input = TtsAudioCreationInput::forInitialJob($job, $audioFile, $family, $resolvedVoice, $licence, $sourceText);

        $result = $this->persistInTransaction($input, function () use ($job): void {
            $this->jobManager->markCompleted($job, false);
        });

        $this->previewMedia->generate($result->masterAudio, $result->masterTmpFile);
        $this->syncPodcastMembershipIfEligible($result->ttsAsset, $result->asset);

        if (null !== $job->getExtRef()->getExtResourceName() && null !== $job->getExtRef()->getExtId()) {
            $this->extSystemCallbackFacade->notifyAssetsChanged(new ArrayCollection([$result->asset]));
        }
    }

    public function processRegenerate(JobAudioNarration $job): void
    {
        $stableAsset = $this->resolveStableAsset($job);
        $stableTts = $this->ttsAssetLocker->requireFor($stableAsset);
        $licence = $this->resolveAssetLicence($job);
        $resolvedVoice = $this->voiceResolver->resolve($job->getVoiceFamilySlug(), $stableAsset->getExtSystem());
        $family = $this->resolveVoiceFamily($resolvedVoice->voiceFamilyId);

        $audioFile = $this->providerContainer->forProvider($resolvedVoice->provider)
            ->synthesize($stableTts->getSourceTextSnapshot(), $resolvedVoice->externalVoiceId, $stableAsset->getExtSystem());

        $this->stageAndSwap($job, $stableAsset, $stableTts, $audioFile, $resolvedVoice, $family, $licence);
    }

    private function stageAndSwap(
        JobAudioNarration $job,
        Asset $stableAsset,
        TtsAsset $stableTts,
        AdapterFile $audioFile,
        ResolvedVoice $resolvedVoice,
        VoiceFamily $family,
        AssetLicence $licence,
    ): void {
        $input = TtsAudioCreationInput::forStagingSwap($job, $stableTts, $audioFile, $family, $resolvedVoice, $licence);

        $stagingResult = $this->persistInTransaction($input);

        $this->previewMedia->generate($stagingResult->masterAudio, $stagingResult->masterTmpFile);

        $swapResult = $this->assetSwap->swap(
            (string) $stagingResult->asset->getId(),
            (string) $stableAsset->getId(),
            (string) $job->getId(),
        );

        $this->jobManager->markCompleted($job);

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

    private function persistInTransaction(TtsAudioCreationInput $input, ?Closure $afterCreate = null): TtsAudioCreationResult
    {
        return $this->entityManager->wrapInTransaction(
            function () use ($input, $afterCreate): TtsAudioCreationResult {
                $created = $this->ttsAudioFactory->create($input);
                $afterCreate?->__invoke();
                $this->entityManager->flush();

                return $created;
            }
        );
    }

    private function resolveStableAsset(JobAudioNarration $job): Asset
    {
        $stableAssetId = (string) $job->getStableAssetId();
        $stableAsset = $this->assetRepo->find($stableAssetId);
        if (null === $stableAsset) {
            throw new RegenCancelledException(
                sprintf('Stable asset "%s" not found for job "%s".', $stableAssetId, (string) $job->getId())
            );
        }

        return $stableAsset;
    }

    /**
     * @throws TtsProviderException if licence is not found
     */
    private function resolveAssetLicence(JobAudioNarration $job): AssetLicence
    {
        $licenceId = $job->getAssetLicenceId();
        if (null === $licenceId) {
            throw new TtsProviderException(sprintf('Job "%s" has no assetLicenceId.', (string) $job->getId()));
        }

        $licence = $this->licenceRepo->find($licenceId);
        if (null === $licence) {
            throw new TtsProviderException(sprintf('AssetLicence "%s" not found for job "%s".', $licenceId, (string) $job->getId()));
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
