<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile\FileProcessor;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\FileSystem\NameGenerator\NameGenerator;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Traits\FileHelperTrait;
use League\Flysystem\FilesystemException;

final class AssetFileStorageOperator
{
    use FileHelperTrait;

    public function __construct(
        private readonly FileSystemProvider $fileSystemProvider,
        private readonly NameGenerator $nameGenerator,
    ) {
    }

    /**
     * @throws FilesystemException
     */
    public function save(AssetFile $assetFile, AdapterFile $file): AssetFile
    {
        $path = $this->nameGenerator->generatePath(
            $this->fileHelper->guessExtension($assetFile->getAssetAttributes()->getMimeType()),
            true
        );
        $fileSystem = $this->fileSystemProvider->getFilesystemByStorable($assetFile);

        $fileSystem->writeStream(
            $path->getRelativePath(),
            $file->readStream(),
        );

        $assetFile->getAssetAttributes()
            ->setFilePath($path->getRelativePath());

        return $assetFile;
    }
}
