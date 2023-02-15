<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFilePositionFacade;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;

/**
 * @template-extends AssetFilePositionFacade<ImageFile>
 */
final class ImagePositionFacade extends AssetFilePositionFacade
{
}
