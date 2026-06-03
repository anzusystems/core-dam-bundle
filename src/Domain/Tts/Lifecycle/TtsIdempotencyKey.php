<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle;

use AnzuSystems\CoreDamBundle\Entity\AssetLicence;

/**
 * Deterministic hash for (licenceId, sourceTextHash, voiceFamilySlug) — backs the UNIQUE index that dedupes
 * in-flight initial jobs. Always returns a non-null string (all inputs are known at dispatch time).
 */
final class TtsIdempotencyKey
{
    public static function forInitial(
        AssetLicence $licence,
        string $sourceTextHash,
        ?string $voiceFamilySlug,
    ): string {
        return hash(
            'sha256',
            sprintf('%s:%s:%s:initial', (string) $licence->getId(), $sourceTextHash, (string) $voiceFamilySlug),
        );
    }
}
