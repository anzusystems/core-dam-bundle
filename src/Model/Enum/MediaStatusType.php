<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Enum;

/**
 * Out-of-band media outcome reported to the consuming ext-system (via the generic media-status callback)
 * for states that cannot be expressed as positive current-state on the regular asset/media sync.
 * Extensible: add cases (e.g. processing/distribution failures) without changing the callback contract.
 *
 * WIRE CONTRACT: the string values are part of the DAM→CMS media-status payload and MUST stay in sync with the
 * consuming app's mirror enum (in core-cms: App\Domain\Media\Model\Enum\DamMediaStatusType). An unknown value
 * is rejected on the receiver (HTTP 422), so a mismatch fails loud rather than corrupting state.
 */
enum MediaStatusType: string
{
    case GenerationFailed = 'generationFailed';
}
