<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exception;

use AnzuSystems\Contracts\Exception\AnzuException;

/** Thrown when mutating an immutable audio narration (e.g. regen while not active). */
final class ImmutableAudioNarrationException extends AnzuException
{
}
