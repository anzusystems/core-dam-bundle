<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Audio;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFilePositionFacade;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;

/**
 * @template-extends AssetFilePositionFacade<AudioFile>
 */
final class AudioPositionFacade extends AssetFilePositionFacade
{
}
