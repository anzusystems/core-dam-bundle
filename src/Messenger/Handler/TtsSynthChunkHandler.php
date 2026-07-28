<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Handler;

use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsRequestFailer;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsSynthesisChunkManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline\TtsRequestOrchestrator;
use AnzuSystems\CoreDamBundle\Entity\TtsSynthesisChunk;
use AnzuSystems\CoreDamBundle\Exception\TtsProviderException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Messenger\Message\TtsSynthChunkMessage;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsChunkStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestStatus;
use AnzuSystems\CoreDamBundle\Repository\TtsSynthesisChunkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/** dam-tts per-chunk worker: synth outside any DB transaction; redelivery absorbed by row-lock claim guard. */
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
            // Not found, already claimed, or parent request no longer Processing — ack & stop.
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

            // Transient provider trouble: re-arm + rethrow for transport redelivery with backoff;
            // repeated failure is bounded by the cleanup-stuck cron's hard cap. findTransient also
            // unwraps a sync transport's wrapping of the next chunk's inline dispatch.
            if (null !== TtsProviderException::findTransient($e)) {
                $this->rearmForRetry($chunk);

                throw $e;
            }

            $this->markChunkFailed($chunk, $e->getMessage());
            $this->requestFailer->fail($request, $e->getMessage());
        }
    }

    /**
     * Best-effort Processing → Pending; when it fails the chunk stays Processing and the
     * cleanup-stuck cron re-arms it after the stale window.
     */
    private function rearmForRetry(TtsSynthesisChunk $chunk): void
    {
        if ($chunk->getStatus()->isNot(TtsChunkStatus::Processing)) {
            return;
        }

        try {
            $this->chunkManager->markPending($chunk);
        } catch (Throwable $rearmEx) {
            $this->logger->warning(DamLogger::NAMESPACE_TTS, 'chunkHandler.rearmFailed', [
                'chunkId' => (string) $chunk->getId(),
                'error' => $rearmEx->getMessage(),
            ]);
        }
    }

    /**
     * Atomic Pending → Processing under row lock; bails early if parent request is cancelled/terminal.
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
        } catch (Throwable $markEx) {
            // Best-effort observability; the request-level failure is the source of truth.
            $this->logger->warning(DamLogger::NAMESPACE_TTS, 'chunkHandler.markFailedFailed', [
                'chunkId' => (string) $chunk->getId(),
                'error' => $markEx->getMessage(),
            ]);
        }
    }
}
