<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileFacade;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFilePositionFacade;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;

/**
 * @template-extends AssetFileFacade<ImageFile>
 */
final class ImagePositionFacade extends AssetFilePositionFacade
{
}
