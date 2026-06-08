<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestStatus;
use DateTimeImmutable;

/**
 * State transitions for {@see TtsNarrationRequest}; clears `initialIdempotencyKey` on every terminal transition.
 * Defaults flush=true (Messenger handler runs each transition standalone); callers inside a transaction pass false.
 */
final class TtsNarrationRequestManager extends AbstractManager
{
    public function create(TtsNarrationRequest $request, bool $flush = true): TtsNarrationRequest
    {
        $this->trackCreation($request);
        $this->entityManager->persist($request);
        $this->flush($flush);

        return $request;
    }

    public function markProcessing(TtsNarrationRequest $request, bool $flush = true): TtsNarrationRequest
    {
        $request->setStatus(TtsRequestStatus::Processing);
        $request->setStartedAt(new DateTimeImmutable());
        $this->trackModification($request);
        $this->flush($flush);

        return $request;
    }

    /**
     * Re-arm a stalled Processing request to Waiting; clears startedAt so the stall window restarts.
     */
    public function markWaiting(TtsNarrationRequest $request, bool $flush = true): TtsNarrationRequest
    {
        $request->setStatus(TtsRequestStatus::Waiting);
        $request->setStartedAt(null);
        $this->trackModification($request);
        $this->flush($flush);

        return $request;
    }

    public function markDone(TtsNarrationRequest $request, bool $flush = true): TtsNarrationRequest
    {
        return $this->finalize($request, TtsRequestStatus::Done, null, $flush);
    }

    public function markFailed(TtsNarrationRequest $request, string $reason, bool $flush = true): TtsNarrationRequest
    {
        return $this->finalize($request, TtsRequestStatus::Failed, $reason, $flush);
    }

    public function markCancelled(TtsNarrationRequest $request, bool $flush = false): TtsNarrationRequest
    {
        return $this->finalize($request, TtsRequestStatus::Cancelled, null, $flush);
    }

    /**
     * Cooperative cancel signal — orchestrator checks {@see TtsNarrationRequest::isCancelRequested()}
     * before destructive swap and aborts. Does NOT transition status (pre-terminal flag).
     */
    public function markCancellationRequested(TtsNarrationRequest $request, bool $flush = false): TtsNarrationRequest
    {
        $request->setCancelRequested(true);
        $this->trackModification($request);
        $this->flush($flush);

        return $request;
    }

    /**
     * Clears initialIdempotencyKey (frees the slot) and nullifies sourceText on every terminal transition.
     */
    private function finalize(TtsNarrationRequest $request, TtsRequestStatus $terminal, ?string $reason, bool $flush): TtsNarrationRequest
    {
        if ($request->getStatus()->in(TtsRequestStatus::TERMINAL_STATUSES)) {
            return $request;
        }

        $request->setStatus($terminal);
        $request->setFailureReason($reason);
        $request->setInitialIdempotencyKey(null);
        $request->setSourceText(null);
        $this->trackModification($request);
        $this->flush($flush);

        return $request;
    }
}
