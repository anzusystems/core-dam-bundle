<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle;

use AnzuSystems\CoreDamBundle\Model\Enum\TtsAudioStatus;

/**
 * Allowed TTS asset statuses per operation — one place for the locking entry-point whitelists.
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
