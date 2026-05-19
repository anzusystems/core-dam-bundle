<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestStatus;
use DateTimeImmutable;

/**
 * State transitions for {@see TtsNarrationRequest}. `openInitialKey` is cleared on every terminal
 * transition (Done / Failed / Cancelled) — enforced here so callers can't forget.
 *
 * Flush convention: defaults to `flush = true` because the orchestrator updates state outside any
 * caller transaction (the Messenger handler runs each transition standalone — see
 * {@see \AnzuSystems\CoreDamBundle\Messenger\Handler\TtsNarrationRequestHandler}). Callers inside a
 * transaction (e.g. {@see \AnzuSystems\CoreDamBundle\Domain\Tts\Command\CancelRequest}) pass `false`.
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

    public function markDone(TtsNarrationRequest $request, string $resultAssetId, bool $flush = true): TtsNarrationRequest
    {
        $request->setResultAssetId($resultAssetId);

        return $this->finalize($request, TtsRequestStatus::Done, null, $flush);
    }

    public function markFailed(TtsNarrationRequest $request, string $reason, bool $flush = true): TtsNarrationRequest
    {
        return $this->finalize($request, TtsRequestStatus::Failed, $reason, $flush);
    }

    public function markCancelled(TtsNarrationRequest $request, ?string $reason = null, bool $flush = false): TtsNarrationRequest
    {
        return $this->finalize($request, TtsRequestStatus::Cancelled, $reason, $flush);
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

    private function finalize(TtsNarrationRequest $request, TtsRequestStatus $terminal, ?string $reason, bool $flush): TtsNarrationRequest
    {
        $request->setStatus($terminal);
        $request->setFinishedAt(new DateTimeImmutable());
        $request->setFailureReason($reason);
        $request->setOpenInitialKey(null);
        $this->trackModification($request);
        $this->flush($flush);

        return $request;
    }
}
