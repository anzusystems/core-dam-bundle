<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileProcessor\AssetFileStorageOperator;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFileCopyBuilder;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use League\Flysystem\FilesystemException;

final readonly class AssetFileCopyBuilder
{
    public function __construct(
        private AssetFileStorageOperator $assetFileStorageOperator,
        private ImageFileCopyBuilder $imageFileCopyBuilder,
    ) {
    }

    /**
     * @throws FilesystemException
     */
    public function copy(AssetFile $assetFile, AssetFile $targetAssetFile): void
    {
        $targetAssetFile->setAssetAttributes(clone $assetFile->getAssetAttributes());
        $this->assetFileStorageOperator->copyToAssetFile($assetFile, $targetAssetFile);
        if ($assetFile instanceof ImageFile && $targetAssetFile instanceof ImageFile) {
            $this->imageFileCopyBuilder->copy($assetFile, $targetAssetFile);
        }

        // todo exception invalid combination
    }
}
