<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile\FileProcessor;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Helper\FileHelper;
use AnzuSystems\CoreDamBundle\Model\Dto\File\File;

final class FileAttributesProcessor
{
    public function process(AssetFile $assetFile, File $file): AssetFile
    {
        $checksum = FileHelper::checksumFromPath($file->getRealPath());

        $assetFile->getAssetAttributes()
            ->setMimeType((string) $file->getMimeType())
            ->setSize($file->getSize())
            ->setChecksum($checksum);

        return $assetFile;
    }
}
