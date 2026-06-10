<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Enum;

/**
 * Out-of-band media outcome for the DAM→CMS media-status callback.
 * WIRE CONTRACT: string values MUST stay in sync with core-cms DamMediaStatusType; mismatch → HTTP 422.
 */
enum MediaStatusType: string
{
    case GenerationFailed = 'generationFailed';
}
