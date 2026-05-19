<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle;

use AnzuSystems\CoreDamBundle\Model\Enum\TtsAudioStatus;

/**
 * Single source of truth for which TTS asset statuses are valid entry points for each operation.
 * Use cases reference these whitelists when locking the asset so the allowed transitions live in one place.
 */
final class TtsLifecycle
{
    /**
     * Operations that require the asset to be live (regen, replace-upload).
     */
    public const array ACTIVE_ONLY = [TtsAudioStatus::Active];

    /**
     * Cancel-regen phase 1 — only assets currently in the staging window.
     */
    public const array SUPERSEDING_ONLY = [TtsAudioStatus::Superseding];
}
