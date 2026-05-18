<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle;

use AnzuSystems\CoreDamBundle\Entity\ExtSystem;

/**
 * Deterministic hash for (extResourceName, extId, extSystem) — backs the UNIQUE index that dedupes
 * in-flight initial jobs. Returns null for manual dispatches without an ext tuple.
 */
final class TtsIdempotencyKey
{
    public static function forInitial(
        ?string $extResourceName,
        ?string $extId,
        ExtSystem $extSystem,
    ): ?string {
        if (null === $extResourceName || null === $extId) {
            return null;
        }

        return hash(
            'sha256',
            sprintf('%s:%s:%s:initial', $extResourceName, $extId, (string) $extSystem->getId()),
        );
    }
}
