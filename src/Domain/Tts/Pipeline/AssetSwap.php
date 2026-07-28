<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline;

use AnzuSystems\CoreDamBundle\Domain\AssetSlot\AssetSlotFactory;
use AnzuSystems\CoreDamBundle\Domain\Tts\Config;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetLocker;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsLocker;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsNarrationRequestManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Entity\Voice;
use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;
use AnzuSystems\CoreDamBundle\Exception\RegenCancelledException;
use AnzuSystems\CoreDamBundle\Logger\TtsAuditLogger;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsAudioStatus;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/** Atomic regen promote: slots new audio, demotes old with grace expireAt; aborts on concurrent cancel. */
final readonly class AssetSwap
{
    public function __construct(
        private TtsAssetLocker $assetLocker,
        private AssetSlotFactory $assetSlotFactory,
        private TtsAssetManager $ttsAssetManager,
        private TtsNarrationRequestManager $requestManager,
        private TtsAuditLogger $auditLogger,
        private Config $config,
        private EntityManagerInterface $entityManager,
        private TtsLocker $ttsLocker,
    ) {
    }

    /**
     * @throws RegenCancelledException if the swap is aborted due to cancel request or wrong status
     */
    public function promote(
        string $stableAssetId,
        AudioFile $newMaster,
        ?AudioFile $newPreview,
        TtsNarrationRequest $request,
        Voice $voice,
        VoiceFamily $family,
    ): void {
        // Asset lock pairs with the two-phase regen cancel; request lock pairs with the failer's
        // terminal guard. Ordering asset → request (cancel takes asset only, failer request only).
        $this->ttsLocker->withAssetLock(
            $stableAssetId,
            function () use ($stableAssetId, $newMaster, $newPreview, $request, $voice, $family): void {
                $this->ttsLocker->withRequestLock(
                    $request,
                    function () use ($stableAssetId, $newMaster, $newPreview, $request, $voice, $family): void {
                        $this->entityManager->wrapInTransaction(
                            fn () => $this->swap($stableAssetId, $newMaster, $newPreview, $request, $voice, $family)
                        );
                    },
                );
            },
        );
    }

    private function swap(
        string $stableAssetId,
        AudioFile $newMaster,
        ?AudioFile $newPreview,
        TtsNarrationRequest $request,
        Voice $voice,
        VoiceFamily $family,
    ): void {
        $stableTts = $this->lockAndValidate($stableAssetId, $request);
        $stableAsset = $stableTts->getAsset();

        $expireAt = $this->config->getAudioRetentionExpireAt();

        $demoted = array_values(array_filter([
            $this->demoteAndReplace($stableAsset, $newMaster, $this->config->getMasterSlotName(), $expireAt),
            null !== $newPreview ? $this->demoteAndReplace($stableAsset, $newPreview, $this->config->getPreviewSlotName(), $expireAt) : null,
        ]));

        $stableTts
            ->setVoiceFamily($family)
            ->setProvider($voice->getDiscriminator())
            ->setExternalVoiceId($voice->getExternalVoiceId())
            ->setMainImageFileId($request->getMainImageFileId() ?? $stableTts->getMainImageFileId())
        ;
        $this->ttsAssetManager->markActive($stableTts);

        // Terminal Done committed atomically with the swap — a failure/cancel callback can never
        // follow a promoted swap.
        $this->requestManager->markDone($request, flush: false);

        $newIds = array_values(array_filter([
            (string) $newMaster->getId(),
            null !== $newPreview ? (string) $newPreview->getId() : null,
        ]));
        $this->auditLogger->logSwapped(
            assetId: $stableAssetId,
            requestId: (string) $request->getId(),
            oldAudioFileIds: $demoted,
            newAudioFileIds: $newIds,
            voiceFamilySlug: $family->getSlug(),
            sourceTextHash: $stableTts->getSourceTextHash(),
        );

        $this->entityManager->flush();
    }

    /**
     * @return string|null the demoted file id (null when the slot had no previous file)
     */
    private function demoteAndReplace(Asset $stableAsset, AudioFile $newFile, string $slotName, DateTimeImmutable $expireAt): ?string
    {
        $previous = $this->assetSlotFactory->replaceSlotFile($stableAsset, $newFile, $slotName);
        // Now live on slot — clear the safety expireAt.
        $newFile->setExpireAt(null);

        if (null === $previous) {
            return null;
        }

        $previous->setExpireAt($expireAt);

        return (string) $previous->getId();
    }

    /**
     * Row state is current: the locker's locked read re-hydrates (HINT_REFRESH) and the request was
     * refreshed by the surrounding withRequestLock().
     *
     * @throws RegenCancelledException
     */
    private function lockAndValidate(string $stableAssetId, TtsNarrationRequest $request): TtsAsset
    {
        $stableTts = $this->assetLocker->lock($stableAssetId);

        $currentStatus = $stableTts->getStatus();

        if ($currentStatus->isNot(TtsAudioStatus::Superseding) || $request->isCancelRequested()) {
            throw new RegenCancelledException(
                sprintf(
                    'Swap aborted for asset "%s": status="%s", cancelRequested=%s.',
                    $stableAssetId,
                    $currentStatus->value,
                    $request->isCancelRequested() ? 'true' : 'false',
                )
            );
        }

        return $stableTts;
    }
}
