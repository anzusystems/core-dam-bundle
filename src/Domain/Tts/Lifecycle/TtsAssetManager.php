<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsAudioStatus;
use DateTimeImmutable;

/**
 * Owns TtsAsset state transitions via named mark* methods — keeps allowed transitions in code.
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

    public function markSuperseding(TtsAsset $ttsAsset, string $regenJobId, bool $flush = false): TtsAsset
    {
        $ttsAsset->setStatus(TtsAudioStatus::Superseding);
        $ttsAsset->setRegenJobId($regenJobId);
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
        $ttsAsset->setRegenJobId(null);
        $this->trackModification($ttsAsset);
        $this->flush($flush);

        return $ttsAsset;
    }

    public function markUnpublished(TtsAsset $ttsAsset, ?string $reason, bool $flush = false): TtsAsset
    {
        $ttsAsset->setStatus(TtsAudioStatus::Unpublished);
        $ttsAsset->setFailureReason($reason);
        $this->trackModification($ttsAsset);
        $this->flush($flush);

        return $ttsAsset;
    }

    /**
     * Finalize a successful swap: bring the asset back to Active, clear the regen lock, stamp the
     * last-regenerated timestamp, and detach the staging flag.
     */
    public function markActive(TtsAsset $ttsAsset, bool $flush = false): TtsAsset
    {
        $ttsAsset->setStatus(TtsAudioStatus::Active);
        $ttsAsset->setIsStaging(false);
        $ttsAsset->setRegenJobId(null);
        $ttsAsset->setLastRegeneratedAt(new DateTimeImmutable());
        $this->trackModification($ttsAsset);
        $this->flush($flush);

        return $ttsAsset;
    }

    public function setIncludeInRecommendedPodcast(TtsAsset $ttsAsset, bool $include, bool $flush = false): TtsAsset
    {
        $ttsAsset->setIncludeInRecommendedPodcast($include);
        $this->trackModification($ttsAsset);
        $this->flush($flush);

        return $ttsAsset;
    }
}
