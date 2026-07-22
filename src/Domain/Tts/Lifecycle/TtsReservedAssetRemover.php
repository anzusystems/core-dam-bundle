<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle;

use AnzuSystems\CoreDamBundle\Domain\Asset\AssetManager;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Repository\AudioFileRepository;
use Throwable;

/** Best-effort teardown of the reserved asset (audio + routes + storage + asset row) on failure or initial cancel. */
final readonly class TtsReservedAssetRemover
{
    public function __construct(
        private AssetRepository $assetRepo,
        private TtsAudioFileRemover $audioFileRemover,
        private AssetManager $assetManager,
        private AudioFileRepository $audioFileRepository,
        private DamLogger $logger,
    ) {
    }

    public function remove(?string $assetId, string $requestId): void
    {
        if (null === $assetId) {
            return;
        }

        try {
            $asset = $this->assetRepo->find($assetId);
            if (null === $asset) {
                return;
            }

            $audioFiles = [];
            foreach ($asset->getSlots() as $slot) {
                $audio = $slot->getAudio();
                if (null !== $audio) {
                    $audioFiles[] = $audio;
                }
            }
            foreach ($this->audioFileRepository->findDetachedByAsset($asset) as $detached) {
                $audioFiles[] = $detached;
            }
            $this->audioFileRemover->remove(...$audioFiles);

            $this->assetManager->delete($asset, true);
        } catch (Throwable $deleteEx) {
            $this->logger->warning(DamLogger::NAMESPACE_TTS, 'reservedAssetRemover.deleteFailed', [
                'requestId' => $requestId,
                'assetId' => $assetId,
                'error' => $deleteEx->getMessage(),
            ]);
        }
    }
}
