<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\TtsSynthesisChunk;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsChunkStatus;
use DateTimeImmutable;

/**
 * DB gateway for {@see TtsSynthesisChunk} — every create/update/delete routes through here so timestamps
 * are tracked. Defaults flush=true (handlers run each transition standalone); callers inside a transaction
 * pass false.
 */
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
