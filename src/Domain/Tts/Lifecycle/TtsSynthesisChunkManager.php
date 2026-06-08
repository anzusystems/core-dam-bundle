<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\TtsSynthesisChunk;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsChunkStatus;
use DateTimeImmutable;

/** DB gateway for {@see TtsSynthesisChunk} state transitions; flush=true by default, pass false inside transactions. */
final class TtsSynthesisChunkManager extends AbstractManager
{
    public function create(TtsSynthesisChunk $chunk, bool $flush = true): TtsSynthesisChunk
    {
        $this->trackCreation($chunk);
        $this->entityManager->persist($chunk);
        $this->flush($flush);

        return $chunk;
    }

    public function markProcessing(TtsSynthesisChunk $chunk, bool $flush = true): TtsSynthesisChunk
    {
        $chunk->setStatus(TtsChunkStatus::Processing)->setStartedAt(new DateTimeImmutable());
        $this->trackModification($chunk);
        $this->flush($flush);

        return $chunk;
    }

    public function markDone(TtsSynthesisChunk $chunk, string $mp3StoragePath, ?string $externalRequestId, bool $flush = true): TtsSynthesisChunk
    {
        $chunk->setStatus(TtsChunkStatus::Done)->setMp3StoragePath($mp3StoragePath)->setExternalRequestId($externalRequestId);
        $this->trackModification($chunk);
        $this->flush($flush);

        return $chunk;
    }

    /**
     * Re-arm a stuck Processing chunk to Pending; clears startedAt so stale detection restarts.
     */
    public function markPending(TtsSynthesisChunk $chunk, bool $flush = true): TtsSynthesisChunk
    {
        $chunk->setStatus(TtsChunkStatus::Pending)->setStartedAt(null);
        $this->trackModification($chunk);
        $this->flush($flush);

        return $chunk;
    }

    public function markFailed(TtsSynthesisChunk $chunk, string $reason, bool $flush = true): TtsSynthesisChunk
    {
        $chunk->setStatus(TtsChunkStatus::Failed)->setFailureReason($reason);
        $this->trackModification($chunk);
        $this->flush($flush);

        return $chunk;
    }

    public function delete(TtsSynthesisChunk $chunk, bool $flush = true): void
    {
        $this->entityManager->remove($chunk);
        $this->flush($flush);
    }
}
