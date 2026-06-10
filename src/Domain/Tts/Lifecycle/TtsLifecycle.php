<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle;

use AnzuSystems\CoreDamBundle\Model\Enum\TtsAudioStatus;

/** Locking entry-point status whitelists — one place for all allowed-statuses constants. */
final class TtsLifecycle
{
    /**
     * Required for regen / replace-upload.
     */
    public const array ACTIVE_ONLY = [TtsAudioStatus::Active];

    /**
     * Required for cancel-regen phase 1.
     */
    public const array SUPERSEDING_ONLY = [TtsAudioStatus::Superseding];
}
