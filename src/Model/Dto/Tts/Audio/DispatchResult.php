<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio;

use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Model\Enum\DispatchStatus;

/**
 * Outcome of {@see \AnzuSystems\CoreDamBundle\Domain\Tts\Facade\TtsDispatchFacade}.
 * Callers map `status` directly to HTTP status (Pending → 201, Duplicate → 200, AlreadyPending → 409).
 */
final readonly class DispatchResult
{
    private function __construct(
        public DispatchStatus $status,
        public ?string $existingAssetId = null,
        private ?string $assetId = null,
        public ?TtsNarrationRequest $narrationRequest = null,
    ) {
    }

    public static function pending(string $assetId, TtsNarrationRequest $narrationRequest): self
    {
        return new self(DispatchStatus::Pending, assetId: $assetId, narrationRequest: $narrationRequest);
    }

    /**
     * Content-addressed dedup: identical (licence, source text, voiceFamily) already produced an asset, so no
     * synthesis runs — the existing asset id is handed back for the caller (CMS) to reuse.
     */
    public static function duplicate(string $originAssetId): self
    {
        return new self(DispatchStatus::Duplicate, existingAssetId: $originAssetId);
    }

    public static function alreadyPending(): self
    {
        return new self(DispatchStatus::AlreadyPending);
    }

    /**
     * Returns the target asset id:
     * - Pending: the freshly reserved asset id (the shell created at dispatch)
     * - Duplicate: the existing active asset id
     * - AlreadyPending: null — a concurrent dispatch owns the media attach, this duplicate is a no-op
     */
    public function getAssetId(): ?string
    {
        return match ($this->status) {
            DispatchStatus::Duplicate => $this->existingAssetId,
            default => $this->assetId,
        };
    }
}
