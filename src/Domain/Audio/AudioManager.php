<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Audio;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;

/**
 * @extends AssetFileManager<AudioFile>
 */
final class AudioManager extends AssetFileManager
{
}
