<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline;

use AnzuSystems\CoreDamBundle\Domain\Asset\AssetManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileStash;
use AnzuSystems\CoreDamBundle\Domain\Tts\Config;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
use AnzuSystems\CoreDamBundle\Exception\RegenCancelledException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Logger\TtsAuditLogger;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\SwapResultDto;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsAudioStatus;
use AnzuSystems\CoreDamBundle\Repository\TtsAssetRepository;
use AnzuSystems\CoreDamBundle\Repository\TtsNarrationRequestRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

/**
 * Atomic content-swap: AudioFile rows on the stable Asset are kept; only their storage payload is
 * swapped with the staging Asset's. Stable routes remain valid — only the bytes behind them change.
 * Staging Asset is deleted afterwards (TtsAsset cascades via FK).
 *
 * Caller is responsible for dispatching purge events for files returned in {@see SwapResultDto}.
 */
final readonly class AssetSwap
{
    public function __construct(
        private TtsAssetRepository $ttsAssetRepo,
        private TtsNarrationRequestRepository $requestRepo,
        private AssetFileManager $audioFileManager,
        private TtsAssetManager $ttsAssetManager,
        private TtsAuditLogger $auditLogger,
        private AssetManager $assetManager,
        private Config $config,
        private EntityManagerInterface $entityManager,
        private FileStash $fileStash,
        private DamLogger $logger,
    ) {
    }

    /**
     * @throws RegenCancelledException if the swap is aborted due to cancel request or wrong status
     */
    public function swap(string $stagingAssetId, string $stableAssetId, string $requestId): SwapResultDto
    {
        $result = $this->entityManager->wrapInTransaction(
            function () use ($stagingAssetId, $stableAssetId, $requestId): SwapResultDto {
                [$stableTts, $stagingTts] = $this->lockAndValidate($stagingAssetId, $stableAssetId, $requestId);

                return $this->applySwap($stableTts, $stagingTts, $requestId);
            }
        );

        // The in-place content swap stashes the previous master/preview payloads (and the staging
        // asset's files) for storage deletion; drain it now that the swap is durably committed.
        // Failures must NOT fail an already-completed regen — a leaked file is far less harmful.
        try {
            $this->fileStash->emptyAll();
        } catch (Throwable $e) {
            $this->logger->warning(DamLogger::NAMESPACE_TTS, 'assetSwap.fileStashEmptyFailed', [
                'stableAssetId' => $result->stableAssetId,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * @return array{0: TtsAsset, 1: TtsAsset}
     *
     * @throws RegenCancelledException
     */
    private function lockAndValidate(string $stagingAssetId, string $stableAssetId, string $requestId): array
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

        $stagingTts = $this->ttsAssetRepo->findByAssetIdJoined($stagingAssetId);
        if (null === $stagingTts) {
            throw new RegenCancelledException(sprintf('Staging asset "%s" is not a TTS asset (or does not exist).', $stagingAssetId));
        }

        return [$stableTts, $stagingTts];
    }

    private function applySwap(TtsAsset $stableTts, TtsAsset $stagingTts, string $requestId): SwapResultDto
    {
        $stableAsset = $stableTts->getAsset();
        $stagingAsset = $stagingTts->getAsset();

        $slotPairs = [
            $this->buildSlotPair($stableAsset, $stagingAsset, $this->config->getMasterSlotName()),
            $this->buildSlotPair($stableAsset, $stagingAsset, $this->config->getPreviewSlotName()),
        ];

        $audioFilesToPurge = [];
        $oldAudioFileIds = [];
        $newAudioFileIds = [];

        foreach ($slotPairs as [$old, $new]) {
            if (null !== $old) {
                $audioFilesToPurge[] = $old;
                $oldAudioFileIds[] = (string) $old->getId();
            }
            if (null !== $new) {
                $newAudioFileIds[] = (string) $new->getId();
            }
            if (null !== $old && null !== $new) {
                $this->audioFileManager->swapContent($old, $new);
            }
            if (null !== $new) {
                $this->audioFileManager->delete($new, false);
            }
        }

        $this->assetManager->delete($stagingAsset, false);
        $this->ttsAssetManager->markActive($stableTts);

        $this->auditLogger->logSwapped(
            assetId: (string) $stableAsset->getId(),
            requestId: $requestId,
            oldAudioFileIds: $oldAudioFileIds,
            newAudioFileIds: $newAudioFileIds,
            voiceFamilySlug: $stagingTts->getVoiceFamily()->getSlug(),
            sourceTextHash: $stagingTts->getSourceTextHash(),
        );

        $this->entityManager->flush();

        return new SwapResultDto(
            stableAssetId: (string) $stableAsset->getId(),
            oldAudioFileIds: $oldAudioFileIds,
            newAudioFileIds: $newAudioFileIds,
            audioFilesToPurge: $audioFilesToPurge,
        );
    }

    /**
     * @return array{0: ?AudioFile, 1: ?AudioFile} (stable, staging) pair for the slot
     */
    private function buildSlotPair(Asset $stable, Asset $staging, string $slotName): array
    {
        return [$this->getSlotAudio($stable, $slotName), $this->getSlotAudio($staging, $slotName)];
    }

    private function getSlotAudio(Asset $asset, string $slotName): ?AudioFile
    {
        foreach ($asset->getSlots() as $slot) {
            if ($slot->getName() === $slotName) {
                return $slot->getAudio();
            }
        }

        return null;
    }
}
