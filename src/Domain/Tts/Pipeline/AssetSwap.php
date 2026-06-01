<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\AssetSlot\AssetSlotFactory;
use AnzuSystems\CoreDamBundle\Domain\Tts\Config;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetManager;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
use AnzuSystems\CoreDamBundle\Entity\Voice;
use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;
use AnzuSystems\CoreDamBundle\Exception\RegenCancelledException;
use AnzuSystems\CoreDamBundle\Logger\TtsAuditLogger;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsAudioStatus;
use AnzuSystems\CoreDamBundle\Repository\TtsAssetRepository;
use AnzuSystems\CoreDamBundle\Repository\TtsNarrationRequestRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Atomic regeneration promote: the freshly-synthesised audio (already materialised + published to its own
 * public-bucket path = its own CDN URL) is pointed into the stable asset's master/preview slots, and the
 * previously-active files are DEMOTED — left on the asset with an {@see AssetFile::setExpireAt()} grace stamp
 * so their old public URLs keep streaming until the {@see \AnzuSystems\CoreDamBundle\Command\TtsClearExpiredAudioCommand}
 * cron reaps them. The asset id (and thus the CMS media key) never changes.
 *
 * Runs under a PESSIMISTIC_WRITE lock on the stable TtsAsset and aborts on a concurrent cancel.
 */
final readonly class AssetSwap
{
    public function __construct(
        private TtsAssetRepository $ttsAssetRepo,
        private TtsNarrationRequestRepository $requestRepo,
        private AssetSlotFactory $assetSlotFactory,
        private TtsAssetManager $ttsAssetManager,
        private TtsAuditLogger $auditLogger,
        private Config $config,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws RegenCancelledException if the swap is aborted due to cancel request or wrong status
     */
    public function promote(
        string $stableAssetId,
        AudioFile $newMaster,
        ?AudioFile $newPreview,
        string $requestId,
        Voice $voice,
        VoiceFamily $family,
    ): void {
        $this->entityManager->wrapInTransaction(
            function () use ($stableAssetId, $newMaster, $newPreview, $requestId, $voice, $family): void {
                $stableTts = $this->lockAndValidate($stableAssetId, $requestId);
                $stableAsset = $stableTts->getAsset();

                $expireAt = App::getAppDate()->modify(sprintf('+%d seconds', $this->config->getAudioRetentionGraceSeconds()));

                $demoted = [];
                $previousMaster = $this->assetSlotFactory->replaceSlotFile($stableAsset, $newMaster, $this->config->getMasterSlotName());
                if (null !== $previousMaster) {
                    $previousMaster->setExpireAt($expireAt);
                    $demoted[] = (string) $previousMaster->getId();
                }
                if (null !== $newPreview) {
                    $previousPreview = $this->assetSlotFactory->replaceSlotFile($stableAsset, $newPreview, $this->config->getPreviewSlotName());
                    if (null !== $previousPreview) {
                        $previousPreview->setExpireAt($expireAt);
                        $demoted[] = (string) $previousPreview->getId();
                    }
                }

                $stableTts
                    ->setVoiceFamily($family)
                    ->setDiscriminator($voice->getDiscriminator())
                    ->setExternalVoiceId($voice->getExternalVoiceId())
                ;
                $this->ttsAssetManager->markActive($stableTts);

                $newIds = array_values(array_filter([
                    (string) $newMaster->getId(),
                    null !== $newPreview ? (string) $newPreview->getId() : null,
                ]));
                $this->auditLogger->logSwapped(
                    assetId: $stableAssetId,
                    requestId: $requestId,
                    oldAudioFileIds: $demoted,
                    newAudioFileIds: $newIds,
                    voiceFamilySlug: $family->getSlug(),
                    sourceTextHash: $stableTts->getSourceTextHash(),
                );

                $this->entityManager->flush();
            }
        );
    }

    /**
     * @throws RegenCancelledException
     */
    private function lockAndValidate(string $stableAssetId, string $requestId): TtsAsset
    {
        $stableTts = $this->ttsAssetRepo->findByAssetIdJoined($stableAssetId, LockMode::PESSIMISTIC_WRITE);
        if (null === $stableTts) {
            throw new RegenCancelledException(sprintf('Stable asset "%s" is not a TTS asset (or does not exist).', $stableAssetId));
        }

        $request = $this->requestRepo->find($requestId);
        $currentStatus = $stableTts->getStatus();

        if ($currentStatus->isNot(TtsAudioStatus::Superseding) || $request?->isCancelRequested()) {
            throw new RegenCancelledException(
                sprintf(
                    'Swap aborted for asset "%s": status="%s", cancelRequested=%s.',
                    $stableAssetId,
                    $currentStatus->value,
                    $request?->isCancelRequested() ? 'true' : 'false',
                )
            );
        }

        return $stableTts;
    }
}
