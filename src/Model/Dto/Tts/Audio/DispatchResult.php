<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio;

/**
 * Outcome of {@see \AnzuSystems\CoreDamBundle\Domain\Tts\Facade\TtsDispatchFacade}.
 * Callers map `kind` directly to HTTP status (Pending → 202, AlreadyExists/AlreadyPending → 200).
 */
final readonly class DispatchResult
{
    private function __construct(
        public DispatchKind $kind,
        public ?string $requestId = null,
        public ?string $existingAssetId = null,
        private ?string $assetId = null,
    ) {
    }

    public static function pending(string $requestId, string $assetId): self
    {
        return new self(DispatchKind::Pending, requestId: $requestId, assetId: $assetId);
    }

    public static function alreadyExists(string $existingAssetId): self
    {
        return new self(DispatchKind::AlreadyExists, existingAssetId: $existingAssetId);
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
            DispatchKind::AlreadyExists => $this->existingAssetId,
            default => $this->assetId,
        };
    }
}
