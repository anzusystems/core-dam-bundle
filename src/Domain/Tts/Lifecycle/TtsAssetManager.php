<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsAudioStatus;

/**
 * TtsAsset state transitions via mark* methods. Methods default flush=false — caller owns the transaction.
 */
final class TtsAssetManager extends AbstractManager
{
    public function create(TtsAsset $ttsAsset, bool $flush = false): TtsAsset
    {
        $this->trackCreation($ttsAsset);
        $this->entityManager->persist($ttsAsset);
        $this->flush($flush);

        return $ttsAsset;
    }

    public function delete(TtsAsset $ttsAsset, bool $flush = false): void
    {
        $this->entityManager->remove($ttsAsset);
        $this->flush($flush);
    }

    public function markSuperseding(TtsAsset $ttsAsset, bool $flush = false): TtsAsset
    {
        $ttsAsset->setStatus(TtsAudioStatus::Superseding);
        $this->trackModification($ttsAsset);
        $this->flush($flush);

        return $ttsAsset;
    }

    public function markCancelling(TtsAsset $ttsAsset, bool $flush = false): TtsAsset
    {
        $ttsAsset->setStatus(TtsAudioStatus::Cancelling);
        $this->trackModification($ttsAsset);
        $this->flush($flush);

        return $ttsAsset;
    }

    public function markFailed(TtsAsset $ttsAsset, string $reason, bool $flush = false): TtsAsset
    {
        $ttsAsset->setStatus(TtsAudioStatus::Failed);
        $ttsAsset->setFailureReason($reason);
        $this->trackModification($ttsAsset);
        $this->flush($flush);

        return $ttsAsset;
    }

    public function markUnpublished(TtsAsset $ttsAsset, bool $flush = false): TtsAsset
    {
        $ttsAsset->setStatus(TtsAudioStatus::Unpublished);
        $this->trackModification($ttsAsset);
        $this->flush($flush);

        return $ttsAsset;
    }

    public function markActive(TtsAsset $ttsAsset, bool $flush = false): TtsAsset
    {
        $ttsAsset->setStatus(TtsAudioStatus::Active);
        $this->trackModification($ttsAsset);
        $this->flush($flush);

        return $ttsAsset;
    }
}
