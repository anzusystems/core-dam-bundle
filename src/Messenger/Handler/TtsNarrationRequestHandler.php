<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Handler;

use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsNarrationRequestManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsRequestFailer;
use AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline\TtsRequestOrchestrator;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Messenger\Message\TtsNarrationRequestMessage;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestMode;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestStatus;
use AnzuSystems\CoreDamBundle\Repository\TtsNarrationRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/**
 * `dam-tts` queue worker. Synth (slow HTTP) and ffmpeg run OUTSIDE any DB transaction — only short
 * persist+commit windows hold locks. Request pipeline lives in {@see TtsRequestOrchestrator};
 * terminal failure handling in {@see TtsRequestFailer}.
 */
#[AsMessageHandler]
final readonly class TtsNarrationRequestHandler
{
    public function __construct(
        private TtsNarrationRequestRepository $requestRepo,
        private TtsNarrationRequestManager $requestManager,
        private TtsRequestOrchestrator $orchestrator,
        private TtsRequestFailer $requestFailer,
        private EntityManagerInterface $entityManager,
        private DamLogger $logger,
    ) {
    }

    public function __invoke(TtsNarrationRequestMessage $message): void
    {
        $request = $this->claimForProcessing($message->requestId);
        if (null === $request) {
            // Either not found (logged inside claim) or already past Waiting — another worker
            // claimed it via Pub/Sub redelivery. Ack the message and stop.
            return;
        }

        try {
            match ($request->getMode()) {
                TtsRequestMode::Initial => $this->orchestrator->processInitial($request),
                TtsRequestMode::Regenerate => $this->orchestrator->processRegenerate($request),
            };
        } catch (Throwable $e) {
            $this->logger->error(DamLogger::NAMESPACE_TTS, 'handler.requestFailed', [
                'requestId' => (string) $request->getId(),
                'mode' => $request->getMode()->value,
            ], exception: $e);

            // Never rethrow — Messenger retry would re-process a terminal request (initialIdempotencyKey
            // is already cleared). Callers must dispatch a fresh request for a retry.
            $this->requestFailer->fail($request, $e->getMessage());
        }
    }

    /**
     * Atomic Waiting → Processing transition under a row lock. Required because Pub/Sub may
     * redeliver the same message (ack-deadline expiry, worker crash mid-processing) — without
     * this guard two workers would race and double-synthesise.
     */
    private function claimForProcessing(string $requestId): ?TtsNarrationRequest
    {
        return $this->entityManager->wrapInTransaction(function () use ($requestId): ?TtsNarrationRequest {
            $request = $this->requestRepo->findForUpdate($requestId);
            if (null === $request) {
                $this->logger->error(DamLogger::NAMESPACE_TTS, 'handler.requestNotFound', ['requestId' => $requestId]);

                return null;
            }

            if ($request->getStatus()->isNot(TtsRequestStatus::Waiting)) {
                $this->logger->warning(DamLogger::NAMESPACE_TTS, 'handler.alreadyClaimed', [
                    'requestId' => $requestId,
                    'status' => $request->getStatus()->value,
                ]);

                return null;
            }

            $this->requestManager->markProcessing($request);

            return $request;
        });
    }
}
