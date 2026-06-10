<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio;

use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Model\Enum\DispatchStatus;

/** Outcome of TtsDispatchFacade; status maps to HTTP (Pending→201, Duplicate→200, AlreadyPending→409). */
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
     * Content-addressed dedup: same licence+text+family already has an asset; hands back its id.
     */
    public static function duplicate(string $originAssetId): self
    {
        return new self(DispatchStatus::Duplicate, existingAssetId: $originAssetId);
    }

    /**
     * Same content already in flight — carries its asset id so the caller can attach and await completion.
     */
    public static function alreadyPending(?string $assetId): self
    {
        return new self(DispatchStatus::AlreadyPending, assetId: $assetId);
    }

    public function getAssetId(): ?string
    {
        return match ($this->status) {
            DispatchStatus::Duplicate => $this->existingAssetId,
            default => $this->assetId,
        };
    }
}
