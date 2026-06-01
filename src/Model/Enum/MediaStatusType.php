<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Enum;

/**
 * Out-of-band media outcome reported to the consuming ext-system (via the generic media-status callback)
 * for states that cannot be expressed as positive current-state on the regular asset/media sync.
 * Extensible: add cases (e.g. processing/distribution failures) without changing the callback contract.
 */
enum MediaStatusType: string
{
    case GenerationFailed = 'generationFailed';
}
