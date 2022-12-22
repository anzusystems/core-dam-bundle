<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Video;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileFacade;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFilePositionFacade;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;

/**
 * @template-extends AssetFileFacade<VideoFile>
 */
final class VideoPositionFacade extends AssetFilePositionFacade
{
}
