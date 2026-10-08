<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileProcessor\AssetFileStorageOperator;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFileCopyBuilder;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use League\Flysystem\FilesystemException;

final readonly class AssetFileCopyBuilder
{
    public function __construct(
        private AssetFileStorageOperator $assetFileStorageOperator,
        private ImageFileCopyBuilder $imageFileCopyBuilder,
        private AssetFileSingleUseEnforcer $assetFileSingleUseEnforcer,
    ) {
    }

    /**
     * @throws FilesystemException
     */
    public function copy(AssetFile $assetFile, AssetFile $targetAssetFile): void
    {
        if (false === $assetFile instanceof ImageFile || false === $targetAssetFile instanceof ImageFile) {
            throw new RuntimeException(
                sprintf(
                    'Unsupported copy AssetFile combination. Copy from (%s) to (%s)',
                    $assetFile::class,
                    $targetAssetFile::class
                )
            );
        }

        $targetAssetFile->setAssetAttributes(clone $assetFile->getAssetAttributes());
        // originAssetId means "duplicate without own data" only; a physical copy carries its own file and
        // must not inherit the source's duplicate marker either.
        $targetAssetFile->getAssetAttributes()
            ->setOriginAssetId(App::EMPTY_STRING)
            ->setTakenOverFromId($assetFile->getTakeOverRootId())
        ;
        $targetAssetFile->getFlags()->setSingleUse($assetFile->getFlags()->isSingleUse());
        $this->assetFileSingleUseEnforcer->enforce($targetAssetFile);
        $this->assetFileStorageOperator->copyToAssetFile($assetFile, $targetAssetFile);
        $this->imageFileCopyBuilder->copy($assetFile, $targetAssetFile);
    }
}
