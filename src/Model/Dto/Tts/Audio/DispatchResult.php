<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio;

/**
 * Outcome of {@see \AnzuSystems\CoreDamBundle\Domain\Tts\Command\DispatchNewAudioNarration}. Replaces the
 * stub-JobAudioNarration hack: callers can map `kind` directly to HTTP status without inspecting
 * partially-populated entities.
 */
final readonly class DispatchResult
{
    private function __construct(
        public DispatchKind $kind,
        public ?string $jobId = null,
        public ?string $existingAssetId = null,
    ) {
    }

    public static function pending(string $jobId): self
    {
        return new self(DispatchKind::Pending, jobId: $jobId);
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
