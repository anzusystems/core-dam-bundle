<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Document;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFilePositionFacade;
use AnzuSystems\CoreDamBundle\Entity\DocumentFile;

/**
 * @template-extends AssetFilePositionFacade<DocumentFile>
 */
final class DocumentPositionFacade extends AssetFilePositionFacade
{
}
