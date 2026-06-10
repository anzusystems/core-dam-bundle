<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Handler;

use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsLocker;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsNarrationRequestManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsRequestFailer;
use AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline\TtsRequestOrchestrator;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Exception\TtsProviderException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Messenger\Message\TtsNarrationRequestMessage;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestStatus;
use AnzuSystems\CoreDamBundle\Repository\TtsNarrationRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/** dam-tts worker: synth/ffmpeg run outside any DB transaction; only short persist windows hold locks. */
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
        private TtsLocker $ttsLocker,
    ) {
    }

    public function __invoke(TtsNarrationRequestMessage $message): void
    {
        $request = $this->claimForProcessing($message->requestId);
        if (null === $request) {
            // Not found or already past Waiting (Pub/Sub redelivery race) — ack & stop.
            return;
        }

        try {
            $this->orchestrator->plan($request);
        } catch (Throwable $e) {
            $this->logger->error(DamLogger::NAMESPACE_TTS, 'handler.requestFailed', [
                'requestId' => (string) $request->getId(),
                'mode' => $request->getMode()->value,
            ], exception: $e);

            // Transient provider trouble: release the claim + rethrow for transport redelivery;
            // bounded by the cleanup-stuck cron's hard cap.
            if ($e instanceof TtsProviderException && $e->isTransient()) {
                if ($this->rearmForRedelivery($request)) {
                    throw $e;
                }

                // Request went terminal meanwhile (e.g. cancelled) — nothing to retry or fail.
                return;
            }

            // Permanent failure — never rethrow a terminal request; dispatch a fresh one instead.
            $this->requestFailer->fail($request, $e->getMessage());
        }
    }

    /**
     * Guarded Processing → Waiting under the request lock — a cancel that won during the synth call
     * must not be resurrected as claimable.
     */
    private function rearmForRedelivery(TtsNarrationRequest $request): bool
    {
        return $this->ttsLocker->withRequestLock($request, function () use ($request): bool {
            if ($request->getStatus()->isNot(TtsRequestStatus::Processing)) {
                return false;
            }

            $this->requestManager->markWaiting($request);

            return true;
        });
    }

    /**
     * Atomic Waiting → Processing under row lock; prevents double-synthesis on Pub/Sub redelivery.
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
