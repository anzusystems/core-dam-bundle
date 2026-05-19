<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
use AnzuSystems\CoreDamBundle\Exception\ImmutableAudioNarrationException;
use AnzuSystems\CoreDamBundle\Exception\RegenCancelledException;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsAudioStatus;
use AnzuSystems\CoreDamBundle\Repository\TtsAssetRepository;
use Doctrine\DBAL\LockMode;

/**
 * Resolves the TtsAsset behind an Asset. {@see lock()} takes a PESSIMISTIC_WRITE row lock for the
 * mutate paths; {@see requireFor()} is the non-locking read-side variant.
 */
final readonly class TtsAssetLocker
{
    public function __construct(
        private TtsAssetRepository $ttsAssetRepo,
    ) {
    }

    /**
     * @param non-empty-list<TtsAudioStatus> $allowedStatuses
     *
     * @throws RegenCancelledException          if the asset or its TtsAsset row does not exist
     * @throws ImmutableAudioNarrationException if the TtsAsset status is not in $allowedStatuses
     */
    public function lockExpecting(string $assetId, array $allowedStatuses): TtsAsset
    {
        $ttsAsset = $this->lock($assetId);
        $current = $ttsAsset->getStatus();
        if (false === $current->in($allowedStatuses)) {
            $allowed = implode('|', array_map(static fn (TtsAudioStatus $s): string => $s->value, $allowedStatuses));

            throw new ImmutableAudioNarrationException(sprintf(
                'Asset "%s" is in status "%s"; expected one of [%s].',
                $assetId,
                $current->value,
                $allowed,
            ));
        }

        return $ttsAsset;
    }

    /**
     * @throws RegenCancelledException if the asset or its TtsAsset row does not exist
     */
    public function lock(string $assetId): TtsAsset
    {
        $ttsAsset = $this->ttsAssetRepo->findByAssetIdJoined($assetId, LockMode::PESSIMISTIC_WRITE);
        if (null === $ttsAsset) {
            throw new RegenCancelledException(sprintf('Asset "%s" is not a TTS asset (or does not exist).', $assetId));
        }

        return $ttsAsset;
    }

    /**
     * @throws RegenCancelledException if the asset has no TtsAsset row
     */
    public function requireFor(Asset $asset): TtsAsset
    {
        $ttsAsset = $this->ttsAssetRepo->findByAsset($asset);
        if (null === $ttsAsset) {
            throw new RegenCancelledException(sprintf('Asset "%s" is not a TTS asset.', (string) $asset->getId()));
        }

        return $ttsAsset;
    }
}
