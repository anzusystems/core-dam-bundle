<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio;

use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;

/**
 * Outcome of {@see \AnzuSystems\CoreDamBundle\Domain\Tts\Facade\TtsDispatchFacade}.
 * Callers map `kind` directly to HTTP status (Pending → 201, AlreadyExists/Duplicate → 200, AlreadyPending → 409).
 */
final readonly class DispatchResult
{
    private function __construct(
        public DispatchKind $kind,
        public ?string $requestId = null,
        public ?string $existingAssetId = null,
        private ?string $assetId = null,
        public ?TtsNarrationRequest $narrationRequest = null,
    ) {
    }

    public static function pending(string $requestId, string $assetId, TtsNarrationRequest $narrationRequest): self
    {
        return new self(DispatchKind::Pending, requestId: $requestId, assetId: $assetId, narrationRequest: $narrationRequest);
    }

    public static function alreadyExists(string $existingAssetId): self
    {
        return new self(DispatchKind::AlreadyExists, existingAssetId: $existingAssetId);
    }

    /**
     * PRVÝ BERIE: identical (licence, source text, voiceFamily) already produced an asset, so no
     * synthesis runs — the existing asset id is handed back so the caller (CMS) can reuse it and
     * inform the editor that the narration already exists.
     */
    public static function duplicate(string $originAssetId): self
    {
        return new self(DispatchKind::Duplicate, existingAssetId: $originAssetId);
    }

    public static function alreadyPending(): self
    {
        return new self(DispatchKind::AlreadyPending);
    }

    /**
     * Returns the stable asset id:
     * - Pending: the freshly reserved stableAssetId
     * - AlreadyExists: the existing active asset id
     * - AlreadyPending: null — a concurrent dispatch owns the media attach, this duplicate is a no-op
     */
    public function getAssetId(): ?string
    {
        return match ($this->kind) {
            DispatchKind::AlreadyExists, DispatchKind::Duplicate => $this->existingAssetId,
            default => $this->assetId,
        };
    }
}
