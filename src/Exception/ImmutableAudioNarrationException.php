<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exception;

use AnzuSystems\Contracts\Exception\AnzuException;

/**
 * Thrown when an attempt is made to mutate an audio narration that is immutable
 * (e.g. regen requested while status is not 'active').
 * Also re-exported / used in core-cms for the same semantic.
 */
final class ImmutableAudioNarrationException extends AnzuException
{
}
