<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle;

use AnzuSystems\CoreDamBundle\Entity\AssetLicence;

/**
 * Deterministic idempotency hash for (licenceId, sourceTextHash, voiceFamilySlug); backs the UNIQUE dedup index.
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
