<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio;

/**
 * Outcome of {@see \AnzuSystems\CoreDamBundle\Domain\Tts\Command\DispatchNewAudioNarration}.
 * Callers map `kind` directly to HTTP status (Pending → 202, AlreadyExists/AlreadyPending → 200).
 */
final readonly class DispatchResult
{
    private function __construct(
        public DispatchKind $kind,
        public ?string $requestId = null,
        public ?string $existingAssetId = null,
    ) {
    }

    public static function pending(string $requestId): self
    {
        return new self(DispatchKind::Pending, requestId: $requestId);
    }

    public static function alreadyExists(string $existingAssetId): self
    {
        return new self(DispatchKind::AlreadyExists, existingAssetId: $existingAssetId);
    }

    public static function alreadyPending(): self
    {
        return new self(DispatchKind::AlreadyPending);
    }
}
