<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsAudioStatus;

/**
 * Owns TtsAsset state transitions via named mark* methods — keeps allowed transitions in code.
 * Cross-link to the in-flight {@see TtsNarrationRequest} is via the request's `assetId`
 * pointing here (no back-pointer on TtsAsset).
 *
 * Flush convention: all methods default to `flush = false`. TtsAsset mutations always happen inside
 * a caller-owned transaction (Facade / Pipeline). The caller is responsible for the flush.
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
