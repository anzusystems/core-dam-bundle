<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Handler;

use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsRequestFailer;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsSynthesisChunkManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline\TtsRequestOrchestrator;
use AnzuSystems\CoreDamBundle\Entity\TtsSynthesisChunk;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Messenger\Message\TtsSynthChunkMessage;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsChunkStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestStatus;
use AnzuSystems\CoreDamBundle\Repository\TtsSynthesisChunkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/**
 * `dam-tts` worker, one run per chunk. Synth (slow HTTP) runs OUTSIDE any DB transaction — only the
 * short claim + Done commits hold locks. At-most-once: never rethrows (Messenger has no retry policy
 * here), converts a throw into request failure. Redelivery is absorbed by the row-lock claim guard.
 */
#[AsMessageHandler]
final readonly class TtsSynthChunkHandler
{
    public function __construct(
        private TtsSynthesisChunkRepository $chunkRepo,
        private TtsSynthesisChunkManager $chunkManager,
        private TtsRequestOrchestrator $orchestrator,
        private TtsRequestFailer $requestFailer,
        private EntityManagerInterface $entityManager,
        private DamLogger $logger,
    ) {
    }

    public function __invoke(TtsSynthChunkMessage $message): void
    {
        $chunk = $this->claim($message->chunkId);
        if (null === $chunk) {
            // Not found / already claimed / parent request no longer Processing (cancel/terminal) — ack & stop.
            return;
        }

        $request = $chunk->getRequest();

        try {
            $this->orchestrator->processChunk($chunk);
        } catch (Throwable $e) {
            $this->logger->error(DamLogger::NAMESPACE_TTS, 'chunkHandler.failed', [
                'chunkId' => (string) $chunk->getId(),
                'requestId' => (string) $request->getId(),
                'ordinal' => $chunk->getOrdinal(),
            ], exception: $e);

            $this->markChunkFailed($chunk, $e->getMessage());
            $this->requestFailer->fail($request, $e->getMessage());
        }
    }

    /**
     * Atomic Pending → Processing transition under a row lock; bails if the parent request was cancelled
     * or already moved terminal (stops the chain without spending an HTTP call).
     */
    private function claim(string $chunkId): ?TtsSynthesisChunk
    {
        return $this->entityManager->wrapInTransaction(function () use ($chunkId): ?TtsSynthesisChunk {
            $chunk = $this->chunkRepo->findForUpdate($chunkId);
            if (null === $chunk) {
                $this->logger->error(DamLogger::NAMESPACE_TTS, 'chunkHandler.notFound', ['chunkId' => $chunkId]);

                return null;
            }
            if ($chunk->getStatus()->isNot(TtsChunkStatus::Pending)) {
                return null;
            }
            $request = $chunk->getRequest();
            if ($request->getStatus()->isNot(TtsRequestStatus::Processing) || $request->isCancelRequested()) {
                return null;
            }

            $this->chunkManager->markProcessing($chunk);

            return $chunk;
        });
    }

    private function markChunkFailed(TtsSynthesisChunk $chunk, string $reason): void
    {
        if ($chunk->getStatus()->isNot(TtsChunkStatus::Processing)) {
            return;
        }

        try {
            $this->chunkManager->markFailed($chunk, $reason);
        } catch (Throwable) {
            // Best-effort observability; the request-level failure is the source of truth.
        }
    }
}
