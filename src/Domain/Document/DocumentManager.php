<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Document;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Entity\DocumentFile;

/**
 * @extends AssetFileManager<DocumentFile>
 */
final class DocumentManager extends AssetFileManager
{
}
